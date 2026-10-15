<?php

namespace App\Services\Comunicacion;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\OrigenRecursoIdentidad;
use App\Enums\VarianteIdentidad;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicacion\DatosInstitucionales;
use App\Models\Comunicacion\RecursoIdentidad;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Los logos con su vigencia y los datos de la institución
 * (docs/diseno-comunicacion-social.md, 4).
 *
 * Cada archivo subido es una versión que rige desde una fecha. Las pantallas
 * usan la de hoy; un documento, la vigente a su fecha (D3), porque los PDF no
 * se guardan y se generan en cada descarga: sin esto, un acto de una
 * administración se reimprimiría con el logo de la siguiente.
 */
final class IdentidadInstitucionalService implements IdentidadInstitucionalServiceInterface
{
    /** El disco privado: se sirven por el controlador, con su versión en la URL. */
    public const DISCO = 'local';

    /** Los íconos de la app: favicon, el de Apple y los dos de una app instalada. */
    public const LADOS_ICONO = [32, 180, 192, 512];

    private const MIME_POR_FORMATO = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'];

    public function version(VarianteIdentidad $variante, ?CarbonInterface $fecha = null): ?RecursoIdentidad
    {
        $hoy = Carbon::today()->toDateString();
        $dia = ($fecha ?? Carbon::today())->toDateString();
        $validas = fn () => RecursoIdentidad::query()->validas()->where('variante', $variante->value);

        return $validas()->where('vigente_desde', '<=', $dia)
            ->orderByDesc('vigente_desde')->orderByDesc('id')->first()
            // Antes de la primera: la más antigua que ya rige, nunca una programada.
            ?? $validas()->where('vigente_desde', '<=', $hoy)
                ->orderBy('vigente_desde')->orderBy('id')->first();
    }

    public function resolver(VarianteIdentidad $variante, ?CarbonInterface $fecha = null): ?RecursoIdentidad
    {
        $propia = $this->version($variante, $fecha);
        if ($propia !== null) {
            return $propia;
        }

        $respaldo = $variante->respaldo();

        return $respaldo ? $this->version($respaldo, $fecha) : null;
    }

    public function archivo(VarianteIdentidad $variante, ?CarbonInterface $fecha = null): ?string
    {
        $recurso = $this->resolver($variante, $fecha);

        return $recurso ? $this->rutaDe($recurso) : null;
    }

    public function rutaDe(RecursoIdentidad $recurso): string
    {
        return Storage::disk(self::DISCO)->path($recurso->archivo);
    }

    public function datos(): DatosInstitucionales
    {
        // La siembra la migración; si una base no la tiene, se crea vacía en
        // vez de reventar cada PDF.
        return DatosInstitucionales::query()->orderBy('id')->first()
            ?? DatosInstitucionales::create([
                'nombre_oficial' => '', 'siglas' => '', 'nombre_corto' => '',
                'ruc' => '', 'direccion' => '', 'telefono' => '',
            ]);
    }

    public function estado(): Collection
    {
        $hoy = Carbon::today()->toDateString();

        return collect(VarianteIdentidad::cases())->map(fn (VarianteIdentidad $v) => [
            'variante'    => $v,
            'vigente'     => $this->version($v),
            'en_uso'      => $this->resolver($v)?->variante,
            'programadas' => RecursoIdentidad::query()->validas()->with('subidoPor.servidor')
                ->where('variante', $v->value)->where('vigente_desde', '>', $hoy)
                ->orderBy('vigente_desde')->orderBy('id')->get(),
        ]);
    }

    public function versiones(VarianteIdentidad $variante): Collection
    {
        return RecursoIdentidad::query()->with(['subidoPor.servidor', 'anuladaPor.servidor'])
            ->where('variante', $variante->value)
            ->orderByDesc('vigente_desde')->orderByDesc('id')
            ->get();
    }

    public function subir(VarianteIdentidad $variante, string $ruta, ?string $vigenteDesde, ?string $nota, User $usuario): RecursoIdentidad
    {
        $medidas = $this->validarImagen($variante, $ruta);
        $desde = $this->validarFecha($variante, $vigenteDesde);

        $extension = array_search($medidas['mime'], self::MIME_POR_FORMATO, true);
        $archivo = "identidad/{$variante->value}/".Str::uuid().".{$extension}";
        Storage::disk(self::DISCO)->put($archivo, (string) file_get_contents($ruta));

        return RecursoIdentidad::create([
            'variante'      => $variante,
            'archivo'       => $archivo,
            'mime'          => $medidas['mime'],
            'ancho'         => $medidas['ancho'],
            'alto'          => $medidas['alto'],
            'peso'          => (int) filesize($ruta),
            'vigente_desde' => $desde,
            'origen'        => OrigenRecursoIdentidad::SUBIDA,
            'nota'          => $nota,
            'subido_por'    => $usuario->id,
        ]);
    }

    public function reutilizar(RecursoIdentidad $recurso, ?string $vigenteDesde, User $usuario): RecursoIdentidad
    {
        if ($vigenteDesde === null && $this->version($recurso->variante)?->is($recurso)) {
            throw new ReglaNegocioException('Esa versión ya es la vigente.');
        }

        $desde = $this->validarFecha($recurso->variante, $vigenteDesde);

        // El archivo no se copia: ninguno se modifica ni se borra nunca.
        return RecursoIdentidad::create([
            'variante'      => $recurso->variante,
            'archivo'       => $recurso->archivo,
            'mime'          => $recurso->mime,
            'ancho'         => $recurso->ancho,
            'alto'          => $recurso->alto,
            'peso'          => $recurso->peso,
            'vigente_desde' => $desde,
            'origen'        => OrigenRecursoIdentidad::REUTILIZADA,
            'reutiliza_id'  => $recurso->id,
            'nota'          => $recurso->nota,
            'subido_por'    => $usuario->id,
        ]);
    }

    public function anular(RecursoIdentidad $recurso, User $usuario): RecursoIdentidad
    {
        if ($recurso->anulada_en !== null) {
            throw new ReglaNegocioException('Esa versión ya estaba anulada.');
        }

        // Una que ya rigió salió en documentos: anularla los cambiaría al
        // reimprimirlos. Para volver atrás está «Volver a esta versión».
        if (! $recurso->vigente_desde->isAfter(Carbon::today())) {
            throw new ReglaNegocioException(
                'Solo se puede anular una versión programada, que todavía no rige. '
                .'Para volver a una anterior, use «Volver a esta versión».'
            );
        }

        $recurso->update(['anulada_en' => now(), 'anulada_por' => $usuario->id]);

        return $recurso;
    }

    public function actualizarDatos(array $datos, User $usuario): DatosInstitucionales
    {
        $actuales = $this->datos();
        $actuales->update([...$datos, 'actualizado_por' => $usuario->id]);

        return $actuales;
    }

    public function icono(int $lado): ?string
    {
        if (! in_array($lado, self::LADOS_ICONO, true)) {
            return null;
        }

        $isotipo = $this->version(VarianteIdentidad::ISOTIPO);
        if ($isotipo === null) {
            return null;
        }

        // Uno por versión y tamaño: se genera la primera vez y no cambia.
        $disco = Storage::disk(self::DISCO);
        $destino = "identidad/iconos/{$isotipo->id}-{$lado}.png";
        if (! $disco->exists($destino)) {
            $disco->put($destino, $this->generarIcono($this->rutaDe($isotipo), $lado));
        }

        return $disco->path($destino);
    }

    public function manifiesto(): array
    {
        $variantes = [];
        foreach (VarianteIdentidad::cases() as $v) {
            $recurso = $this->resolver($v);
            $variantes[$v->value] = $recurso ? [
                'version'  => $recurso->id,
                'ruta'     => "identidad/{$v->value}?v={$recurso->id}",
                'ancho'    => $recurso->ancho,
                'alto'     => $recurso->alto,
                // Si no tiene versión propia y se ve la de su respaldo.
                'respaldo' => $recurso->variante !== $v,
            ] : null;
        }

        $isotipo = $this->version(VarianteIdentidad::ISOTIPO);
        $datos = $this->datos();

        return [
            'variantes' => $variantes,
            'iconos'    => $isotipo ? collect(self::LADOS_ICONO)->mapWithKeys(
                fn (int $lado) => [(string) $lado => "identidad/icono/{$lado}?v={$isotipo->id}"]
            )->all() : null,
            'datos'     => $datos->only(['nombre_oficial', 'siglas', 'nombre_corto', 'ruc', 'direccion', 'telefono', 'sitio_web']),
        ];
    }

    /** @return array{mime: string, ancho: int, alto: int} */
    private function validarImagen(VarianteIdentidad $variante, string $ruta): array
    {
        $info = @getimagesize($ruta);
        $aceptados = array_unique(array_map(fn (string $f) => self::MIME_POR_FORMATO[$f], $variante->formatos()));

        if ($info === false || ! in_array($info['mime'], $aceptados, true)) {
            throw new ReglaNegocioException("{$variante->etiqueta()}: el archivo debe ser {$variante->requisito()}.");
        }

        [$ancho, $alto] = $info;
        $minimo = $variante->minimo();

        if ($ancho < $minimo['ancho'] || $alto < $minimo['alto']) {
            throw new ReglaNegocioException(
                "{$variante->etiqueta()}: la imagen mide {$ancho} × {$alto} píxeles y se pide {$variante->requisito()}."
            );
        }

        $proporcion = $variante->proporcion();
        if ($proporcion !== null && abs($ancho / $alto - $proporcion['valor']) / $proporcion['valor'] > $proporcion['tolerancia']) {
            throw new ReglaNegocioException(
                "{$variante->etiqueta()}: la imagen mide {$ancho} × {$alto} píxeles y no tiene la proporción que se pide: {$variante->requisito()}."
            );
        }

        return ['mime' => $info['mime'], 'ancho' => $ancho, 'alto' => $alto];
    }

    /**
     * Desde cuándo rige: hoy si no se dice. No puede empezar antes que la
     * última versión de esa variante, porque reescribiría los documentos que
     * ya se emitieron con ella.
     */
    private function validarFecha(VarianteIdentidad $variante, ?string $vigenteDesde): string
    {
        $desde = $vigenteDesde ? Carbon::parse($vigenteDesde)->startOfDay() : Carbon::today();

        $ultima = RecursoIdentidad::query()->validas()->where('variante', $variante->value)
            ->max('vigente_desde');

        if ($ultima !== null && $desde->lt(Carbon::parse($ultima))) {
            throw new ReglaNegocioException(sprintf(
                '%s: la última versión rige desde el %s, y la nueva no puede empezar antes: '
                .'cambiaría los documentos que ya se emitieron con ella.',
                $variante->etiqueta(), Carbon::parse($ultima)->format('d/m/Y'),
            ));
        }

        return $desde->toDateString();
    }

    /**
     * El isotipo en un cuadrado del lado pedido, centrado y sin deformar. El de
     * Apple (180) va sobre blanco, porque iOS rellena de negro la transparencia.
     */
    private function generarIcono(string $origen, int $lado): string
    {
        $imagen = imagecreatefromstring((string) file_get_contents($origen));
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);

        $icono = imagecreatetruecolor($lado, $lado);
        imagealphablending($icono, false);
        imagesavealpha($icono, true);
        $fondo = $lado === 180
            ? imagecolorallocate($icono, 255, 255, 255)
            : imagecolorallocatealpha($icono, 0, 0, 0, 127);
        imagefill($icono, 0, 0, $fondo);
        // Sobre transparente se copia sin mezclar, para que conserve su
        // transparencia; sobre blanco se mezcla.
        imagealphablending($icono, $lado === 180);

        $escala = $lado / max($ancho, $alto);
        $w = (int) round($ancho * $escala);
        $h = (int) round($alto * $escala);
        imagecopyresampled($icono, $imagen, intdiv($lado - $w, 2), intdiv($lado - $h, 2), 0, 0, $w, $h, $ancho, $alto);
        imagedestroy($imagen);

        ob_start();
        imagepng($icono);
        imagedestroy($icono);

        return (string) ob_get_clean();
    }
}
