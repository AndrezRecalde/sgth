<?php

namespace App\Services\InventarioTi\Carga;

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\SubtipoBien;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Lo que la carga del Excel busca o crea en los catálogos del inventario.
 * Compara sin mayúsculas ni tildes, como los índices de los catálogos, y
 * recuerda lo ya resuelto: 376 filas no son 376 consultas por cada catálogo.
 */
final class CatalogosDeCarga
{
    private const TIPOS = ['COMPUTADOR' => 'Computador', 'PORTATIL' => 'Portátil'];

    private const SUBTIPOS = ['TODO EN UNO' => 'Todo en uno', 'COMPLETO' => 'Completo', 'CPU' => 'CPU', 'MAC' => 'MAC'];

    /** @var array<string, mixed> */
    private array $cache = [];

    /** @var list<array<string, mixed>> */
    public array $contratosCreados = [];

    public function __construct(private readonly EmparejadorServidores $servidores) {}

    public function tipo(string $valor): TipoBien
    {
        $nombre = self::TIPOS[Normaliza::texto($valor)] ?? Str::ucfirst(Str::lower(trim($valor)));

        return $this->recordar("tipo:{$nombre}", fn () => $this->buscarOCrear(TipoBien::class, ['nombre' => $nombre], ['anios_vida_util' => 0]));
    }

    /** El subtipo; ninguno si repite el tipo («PORTATIL / PORTATIL»). */
    public function subtipo(TipoBien $tipo, ?string $valor): ?SubtipoBien
    {
        $clave = Normaliza::texto($valor);
        if ($clave === '' || $clave === Normaliza::texto($tipo->nombre)) {
            return null;
        }
        $nombre = self::SUBTIPOS[$clave] ?? Str::ucfirst(Str::lower(trim((string) $valor)));

        return $this->recordar("subtipo:{$tipo->id}:{$nombre}", fn () => $this->buscarOCrear(SubtipoBien::class, ['tipo_bien_id' => $tipo->id, 'nombre' => $nombre]));
    }

    public function marca(string $valor): Marca
    {
        $nombre = Str::upper(trim(preg_replace('/\s+/', ' ', $valor) ?? $valor));

        return $this->recordar("marca:{$nombre}", fn () => $this->buscarOCrear(Marca::class, ['nombre' => $nombre]));
    }

    /**
     * «PB» y «1ER»…«6TO» son la planta baja y los pisos del Edificio Central;
     * «SM» y «DM», San Mateo y el Dispensario Médico. Los nombres son los del
     * catálogo que siembra la migración de ubicaciones.
     */
    public function ubicacion(?string $piso): ?Ubicacion
    {
        $clave = Normaliza::texto($piso);
        $sedes = config('inventario.carga.sedes');
        $principal = config('inventario.carga.sede_principal');

        [$sede, $nivel] = match (true) {
            $clave === '' => [null, null],
            isset($sedes[$clave]) => [$sedes[$clave], null],
            $clave === 'PB' => [$principal, 'Planta baja'],
            (bool) preg_match('/^(\d+)/', $clave, $m) => [$principal, "Piso {$m[1]}"],
            default => [null, null],
        };

        return $sede === null ? null : $this->recordar("ubicacion:{$sede}:{$nivel}", fn () => $this->buscarOCrear(Ubicacion::class, ['sede' => $sede, 'piso' => $nivel]));
    }

    /** La unidad por las siglas de la columna «AREA» (config/inventario.php). */
    public function unidad(?string $area): ?UnidadAdministrativa
    {
        $nombre = config('inventario.carga.areas')[Normaliza::texto($area)] ?? null;

        return $nombre === null ? null : $this->recordar("unidad:{$nombre}", fn () => UnidadAdministrativa::query()
            ->whereRaw('lower(nombre) = lower(?)', [$nombre])->first());
    }

    /** @param  array{clave: string, proceso: ?string, administrador: ?string, cronograma: list<array>, texto: string}  $vigencia */
    public function contrato(array $vigencia): ContratoVigencia
    {
        return $this->recordar('contrato:' . $vigencia['clave'], function () use ($vigencia) {
            $proceso = $vigencia['proceso']
                ?? Str::limit('Por revisar ' . (count($this->contratosCreados) + 1) . ': ' . $vigencia['texto'], 95, '…');
            $existente = ContratoVigencia::whereRaw('lower(proceso) = lower(?)', [$proceso])->first();
            if ($existente) {
                return $existente;
            }

            $administrador = $vigencia['administrador'] ? $this->servidores->buscar($vigencia['administrador']) : ['servidor' => null, 'motivo' => null];
            $contrato = ContratoVigencia::create([
                'proceso'          => $proceso,
                'administrador_id' => $administrador['servidor']?->id,
                'observaciones'    => "Creado por la carga del Excel de TIC. Texto original: «{$vigencia['texto']}»",
            ]);
            $contrato->cronograma()->createMany($vigencia['cronograma']);

            $this->contratosCreados[] = [
                'proceso'       => $proceso,
                'administrador' => $administrador['servidor'] ? trim("{$administrador['servidor']->apellido} {$administrador['servidor']->nombre}") : ($vigencia['administrador'] ? "Sin emparejar: {$administrador['motivo']}" : '—'),
                'ventanas'      => count($vigencia['cronograma']),
                'texto'         => $vigencia['texto'],
            ];

            return $contrato;
        });
    }

    /**
     * @param  class-string<Model>  $clase
     * @param  array<string, mixed>  $atributos  los que identifican al registro
     */
    private function buscarOCrear(string $clase, array $atributos, array $extra = []): Model
    {
        $consulta = $clase::query();
        foreach ($atributos as $campo => $valor) {
            $valor === null || is_int($valor)
                ? $consulta->where($campo, $valor)
                : $consulta->whereRaw("lower({$campo}) = lower(?)", [$valor]);
        }

        return $consulta->first() ?? $clase::create([...$atributos, ...$extra]);
    }

    private function recordar(string $clave, callable $resolver): mixed
    {
        // `array_key_exists` y no `??=`: una unidad que no existe se recuerda
        // como null, y no se vuelve a buscar en cada fila.
        if (! array_key_exists($clave, $this->cache)) {
            $this->cache[$clave] = $resolver();
        }

        return $this->cache[$clave];
    }
}
