<?php

namespace App\Services\Comunicacion;

use App\Contracts\Comunicacion\CargaFotografiasServiceInterface;
use App\Contracts\Comunicacion\FotografiaServiceInterface;
use App\Enums\OrigenFotografia;
use App\Enums\ResultadoCargaFotografia;
use App\Exceptions\ReglaNegocioException;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * La carga masiva de fotos (docs/diseno-comunicacion-social.md, 5.1).
 *
 * Comunicación toma las fotos en una sesión y nombra cada archivo con la
 * cédula. El diseño hablaba de subir un ZIP; se descartó porque un lote de
 * cientos de fotos supera el tamaño que acepta una petición. En su lugar se
 * eligen los archivos de la carpeta: la simulación recibe solo los nombres, y
 * la carga recibe las fotos en tandas pequeñas.
 */
final class CargaFotografiasService implements CargaFotografiasServiceInterface
{
    /**
     * Fotos por petición. PHP admite 20 archivos (`max_file_uploads`), pero una
     * foto grande tarda en procesarse y la petición no debe pasar del límite
     * de tiempo del servidor.
     */
    public const POR_TANDA = 10;

    public const PESO_MAXIMO_KB = 10240;

    /** Segundos para una tanda de fotos grandes, si el servidor da menos. */
    private const SEGUNDOS_POR_TANDA = 120;

    public function __construct(private readonly FotografiaServiceInterface $fotografias) {}

    public function simular(array $nombres, bool $reemplazar): array
    {
        return $this->informe($this->clasificar($nombres, $reemplazar));
    }

    public function cargar(array $archivos, bool $reemplazar, User $usuario): array
    {
        $this->darTiempo();

        $filas = $this->clasificar(
            array_map(fn (UploadedFile $a) => $a->getClientOriginalName(), $archivos),
            $reemplazar,
        );

        foreach ($filas as $i => $fila) {
            if (! $fila['resultado']->seCarga()) {
                continue;
            }

            try {
                $archivo = $archivos[$i];

                if (! $archivo->isValid()) {
                    throw new ReglaNegocioException('El archivo no llegó completo.');
                }
                if ($archivo->getSize() > self::PESO_MAXIMO_KB * 1024) {
                    throw new ReglaNegocioException('El archivo pesa más de 10 MB.');
                }

                $this->fotografias->subir($fila['servidor'], (string) $archivo->getRealPath(), $usuario, OrigenFotografia::CARGA_MASIVA);
            } catch (ReglaNegocioException $e) {
                $filas[$i]['resultado'] = ResultadoCargaFotografia::ILEGIBLE;
                $filas[$i]['detalle'] = $e->getMessage();
            }
        }

        return $this->informe($filas);
    }

    /**
     * Qué toca hacer con cada archivo, en el orden en que llegaron.
     *
     * @param  list<string>  $nombres
     * @return list<array{archivo: string, cedula: ?string, servidor: ?Servidor, resultado: ResultadoCargaFotografia, detalle: ?string}>
     */
    private function clasificar(array $nombres, bool $reemplazar): array
    {
        $cedulas = array_map(fn (string $nombre) => $this->cedula($nombre), $nombres);

        $servidores = Servidor::query()
            ->whereIn('cedula', array_values(array_filter($cedulas)))
            ->with(['fotografiaVigente', 'unidadAdministrativa:id,nombre'])
            ->get()
            ->keyBy('cedula');

        $vistas = [];
        $filas = [];

        foreach ($nombres as $i => $nombre) {
            $cedula = $cedulas[$i];
            $servidor = $cedula !== null ? $servidores->get($cedula) : null;

            $resultado = match (true) {
                $cedula === null                      => ResultadoCargaFotografia::SIN_CEDULA,
                $servidor === null                    => ResultadoCargaFotografia::SIN_SERVIDOR,
                isset($vistas[$cedula])               => ResultadoCargaFotografia::REPETIDA,
                ! $servidor->estado                   => ResultadoCargaFotografia::INACTIVO,
                $servidor->fotografiaVigente === null => ResultadoCargaFotografia::NUEVA,
                $reemplazar                           => ResultadoCargaFotografia::REEMPLAZA,
                default                               => ResultadoCargaFotografia::YA_TIENE_FOTO,
            };

            if ($servidor !== null) {
                $vistas[$cedula] = true;
            }

            $filas[] = [
                'archivo'   => $nombre,
                'cedula'    => $cedula,
                'servidor'  => $servidor,
                'resultado' => $resultado,
                'detalle'   => null,
            ];
        }

        return $filas;
    }

    /**
     * Los primeros 10 dígitos seguidos del nombre: sirve `0801234567.jpg` y
     * también `0801234567 - Ana Pérez.jpg`. Si el archivo viene de una carpeta,
     * se mira solo su nombre.
     */
    private function cedula(string $nombre): ?string
    {
        $base = pathinfo(basename(str_replace('\\', '/', $nombre)), PATHINFO_FILENAME);

        return preg_match('/(?<!\d)(\d{10})(?!\d)/', $base, $m) === 1 ? $m[1] : null;
    }

    /**
     * @param  list<array{archivo: string, cedula: ?string, servidor: ?Servidor, resultado: ResultadoCargaFotografia, detalle: ?string}>  $filas
     * @return array{resumen: array{total: int, se_cargan: int, se_omiten: int}, archivos: list<array<string, mixed>>}
     */
    private function informe(array $filas): array
    {
        $seCargan = count(array_filter($filas, fn (array $f) => $f['resultado']->seCarga()));

        return [
            'resumen' => [
                'total'     => count($filas),
                'se_cargan' => $seCargan,
                'se_omiten' => count($filas) - $seCargan,
            ],
            'archivos' => array_map(fn (array $f) => [
                'archivo'            => $f['archivo'],
                'cedula'             => $f['cedula'],
                'servidor'           => $f['servidor'] ? [
                    'id'              => $f['servidor']->id,
                    'nombre_completo' => $f['servidor']->nombre_completo,
                    'unidad'          => $f['servidor']->unidadAdministrativa?->nombre,
                ] : null,
                'resultado'          => $f['resultado']->value,
                'resultado_etiqueta' => $f['resultado']->etiqueta(),
                'se_carga'           => $f['resultado']->seCarga(),
                'detalle'            => $f['detalle'],
            ], $filas),
        ];
    }

    /**
     * Sube el límite de tiempo solo si el servidor da menos. En la consola y en
     * la suite de pruebas corre sin límite (0), y ponerle uno la haría caer.
     */
    private function darTiempo(): void
    {
        $limite = (int) ini_get('max_execution_time');

        if ($limite > 0 && $limite < self::SEGUNDOS_POR_TANDA) {
            set_time_limit(self::SEGUNDOS_POR_TANDA);
        }
    }
}
