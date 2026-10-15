<?php

namespace App\Http\Controllers\Comunicacion;

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\VarianteIdentidad;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Los logos vigentes hoy, sin sesión (docs/diseno-comunicacion-social.md, 4.6;
 * fase 2.1). Los necesitan el login, la verificación pública y los correos, que
 * no tienen sesión; y no son reservados: salen en cualquier documento.
 *
 * Una versión programada no se sirve aquí hasta que rige. La URL lleva la
 * versión (`?v=`): con ella el navegador guarda el archivo para siempre, y un
 * logo nuevo tiene otra.
 */
final class IdentidadPublicaController extends Controller
{
    public function __construct(private readonly IdentidadInstitucionalServiceInterface $identidad) {}

    public function manifiesto(): JsonResponse
    {
        return ApiResponse::ok($this->identidad->manifiesto(), 'Identidad institucional vigente.')
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function variante(Request $request, VarianteIdentidad $variante): BinaryFileResponse|JsonResponse
    {
        $recurso = $this->identidad->resolver($variante);
        $ruta = $recurso ? $this->identidad->rutaDe($recurso) : null;

        if ($ruta === null || ! is_file($ruta)) {
            return ApiResponse::noEncontrado("No hay {$variante->etiqueta()} cargado.");
        }

        return response()->file($ruta, [
            'Content-Type'  => $recurso->mime,
            'Cache-Control' => $this->cache($request, $recurso->id),
        ]);
    }

    public function icono(Request $request, int $lado): BinaryFileResponse|JsonResponse
    {
        $ruta = $this->identidad->icono($lado);
        $isotipo = $this->identidad->version(VarianteIdentidad::ISOTIPO);

        if ($ruta === null || $isotipo === null) {
            return ApiResponse::noEncontrado('No hay isotipo cargado para el ícono.');
        }

        return response()->file($ruta, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => $this->cache($request, $isotipo->id),
        ]);
    }

    /** Con la versión exacta en la URL, para siempre; sin ella, cinco minutos. */
    private function cache(Request $request, int $version): string
    {
        return (string) $request->query('v') === (string) $version
            ? 'public, max-age=31536000, immutable'
            : 'public, max-age=300';
    }
}
