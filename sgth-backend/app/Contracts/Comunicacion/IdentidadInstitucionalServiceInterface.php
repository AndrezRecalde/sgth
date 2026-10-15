<?php

namespace App\Contracts\Comunicacion;

use App\Enums\VarianteIdentidad;
use App\Models\Comunicacion\DatosInstitucionales;
use App\Models\Comunicacion\RecursoIdentidad;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * La identidad institucional (docs/diseno-comunicacion-social.md, 4): los
 * logos con su vigencia y los datos de la institución. Lo que piden las
 * pantallas y, desde la fase 2.2, los documentos PDF.
 */
interface IdentidadInstitucionalServiceInterface
{
    /**
     * La versión de esa variante vigente a la fecha (hoy, sin fecha), sin
     * respaldo. Antes de la primera versión se usa la más antigua: un
     * documento viejo siempre tiene logo.
     */
    public function version(VarianteIdentidad $variante, ?CarbonInterface $fecha = null): ?RecursoIdentidad;

    /** Igual, pero si la variante no tiene versión cae en su respaldo (V3 y V4 en V2). */
    public function resolver(VarianteIdentidad $variante, ?CarbonInterface $fecha = null): ?RecursoIdentidad;

    /** La ruta en el disco del archivo que corresponde, con respaldo; null si no hay. */
    public function archivo(VarianteIdentidad $variante, ?CarbonInterface $fecha = null): ?string;

    public function rutaDe(RecursoIdentidad $recurso): string;

    public function datos(): DatosInstitucionales;

    /** Cada variante con su versión vigente, la que se usa hoy y las programadas. */
    public function estado(): Collection;

    /** Todas las versiones de una variante, de la más reciente a la más antigua. */
    public function versiones(VarianteIdentidad $variante): Collection;

    /** Valida el archivo contra lo que pide la variante y lo deja como versión nueva. */
    public function subir(VarianteIdentidad $variante, string $ruta, ?string $vigenteDesde, ?string $nota, User $usuario): RecursoIdentidad;

    /** «Volver a esta versión»: una versión nueva con el archivo de una anterior. */
    public function reutilizar(RecursoIdentidad $recurso, ?string $vigenteDesde, User $usuario): RecursoIdentidad;

    /** Anula una versión programada, que todavía no rigió. */
    public function anular(RecursoIdentidad $recurso, User $usuario): RecursoIdentidad;

    public function actualizarDatos(array $datos, User $usuario): DatosInstitucionales;

    /** Un ícono cuadrado de la app, sacado del isotipo vigente hoy. */
    public function icono(int $lado): ?string;

    /** Lo que ven las pantallas sin sesión (login, verificación): rutas y datos. */
    public function manifiesto(): array;
}
