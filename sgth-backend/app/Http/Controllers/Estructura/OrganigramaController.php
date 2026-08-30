<?php

namespace App\Http\Controllers\Estructura;

use App\Contracts\Estructura\EstructuraServiceInterface;
use App\Enums\Permiso;
use App\Http\Controllers\Controller;
use App\Http\Resources\Estructura\UnidadAdministrativaResource;
use App\Http\Resources\Estructura\UnidadOrganigramaPublicaResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganigramaController extends Controller
{
    public function __construct(
        private readonly EstructuraServiceInterface $estructuraService
    ) {}

    /**
     * El organigrama es información pública: cualquiera puede leer la
     * estructura de la institución sin tener usuario del sistema.
     *
     * La misma ruta responde con dos niveles de detalle. Sin sesión —o con una
     * que no gestiona estructura— devuelve solo el árbol de unidades y
     * subprocesos. Con permiso de `ver-estructura` agrega lo que hace falta
     * para trabajar con él: conteo de puestos y quién despacha hoy por
     * subrogación o encargo. Escalar el detalle en vez de partir la ruta en
     * dos evita que la pantalla interna y la pública se desincronicen.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // El guard se consulta a mano y no por middleware: la ruta vive en el
        // grupo público, así que `$request->user()` no está resuelto aunque
        // venga un token válido en la cabecera.
        $usuario = Auth::guard('sanctum')->user();

        if ($usuario?->can(Permiso::VER_ESTRUCTURA->value)) {
            return ApiResponse::ok(
                UnidadAdministrativaResource::collection(
                    $this->estructuraService->obtenerOrganigrama()
                ),
                'Organigrama institucional obtenido exitosamente'
            );
        }

        return ApiResponse::ok(
            UnidadOrganigramaPublicaResource::collection(
                $this->estructuraService->obtenerOrganigramaPublico()
            ),
            'Organigrama institucional obtenido exitosamente'
        );
    }
}
