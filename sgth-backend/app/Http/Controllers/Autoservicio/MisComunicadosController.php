<?php

namespace App\Http\Controllers\Autoservicio;

use App\Contracts\Comunicados\ArchivosComunicadoServiceInterface;
use App\Contracts\Comunicados\MisComunicadosServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicados\ListarMisComunicadosRequest;
use App\Http\Resources\Comunicados\ComunicadoPortalResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * «Noticias y comunicados» del Portal (docs/diseno-noticias-comunicados.md, 5):
 * lo que Talento Humano publicó para quien pregunta. Lo que no le corresponde
 * —por unidad, por régimen, retirado o todavía programado— responde 404.
 */
final class MisComunicadosController extends Controller
{
    public function __construct(
        private readonly MisComunicadosServiceInterface $comunicados,
        private readonly ArchivosComunicadoServiceInterface $archivos,
    ) {}

    public function index(ListarMisComunicadosRequest $request): JsonResponse
    {
        $pagina = $this->comunicados->listar($request->user(), $request->validated());

        return ApiResponse::paginadoDe(
            $pagina,
            ComunicadoPortalResource::collection($pagina->getCollection())->resolve($request),
            'Mis noticias y comunicados.',
        );
    }

    /** Lo del inicio y la campana: las cinco primeras, las no leídas y las cifras. */
    public function resumen(Request $request): JsonResponse
    {
        $resumen = $this->comunicados->resumen($request->user());

        return ApiResponse::ok([
            'recientes'     => ComunicadoPortalResource::collection($resumen['recientes'])->resolve($request),
            'no_leidos'     => ComunicadoPortalResource::collection($resumen['no_leidos'])->resolve($request),
            'sin_leer'      => $resumen['sin_leer'],
            'por_confirmar' => $resumen['por_confirmar'],
        ], 'Resumen de noticias y comunicados.');
    }

    /** Abrirla la deja leída. */
    public function show(Request $request, int $comunicado): JsonResponse
    {
        return ApiResponse::ok(
            new ComunicadoPortalResource($this->comunicados->abrir($request->user(), $comunicado)),
            'Detalle de la publicación.',
        );
    }

    public function confirmar(Request $request, int $comunicado): JsonResponse
    {
        return ApiResponse::ok(
            new ComunicadoPortalResource($this->comunicados->confirmar($request->user(), $comunicado)),
            'Confirmó la lectura.',
        );
    }

    public function adjunto(Request $request, int $comunicado, int $adjunto): BinaryFileResponse|JsonResponse
    {
        $archivo = $this->comunicados->adjunto($request->user(), $comunicado, $adjunto);
        $ruta = $this->archivos->rutaAdjunto($archivo);

        if ($ruta === null) {
            return ApiResponse::noEncontrado('El documento no está en el servidor.');
        }

        return response()->download($ruta, $archivo->nombre_original, [
            'Content-Type'           => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
