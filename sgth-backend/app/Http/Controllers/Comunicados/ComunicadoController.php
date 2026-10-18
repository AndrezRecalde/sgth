<?php

namespace App\Http\Controllers\Comunicados;

use App\Contracts\Comunicados\ComunicadoServiceInterface;
use App\Contracts\Comunicados\DestinatariosComunicadoServiceInterface;
use App\Enums\AudienciaComunicado;
use App\Enums\VigenciaComunicado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicados\ActualizarPublicacionRequest;
use App\Http\Requests\Comunicados\ContarDestinatariosRequest;
use App\Http\Requests\Comunicados\GuardarComunicadoRequest;
use App\Http\Requests\Comunicados\ListarComunicadosRequest;
use App\Http\Requests\Comunicados\RetirarComunicadoRequest;
use App\Http\Resources\Comunicados\ComunicadoResource;
use App\Http\Responses\ApiResponse;
use App\Models\Comunicados\Comunicado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Las noticias y comunicados en manos de Talento Humano
 * (docs/diseno-noticias-comunicados.md, 6 y 7.1).
 *
 * Cada acción lleva su permiso en la ruta: redactar, publicar o ver las
 * lecturas. Lo que depende del estado —solo el borrador se edita, solo lo
 * publicado se retira— lo decide ComunicadoService.
 */
final class ComunicadoController extends Controller
{
    private const RELACIONES_DETALLE = [
        'unidadesSeleccionadas:id,nombre', 'adjuntos',
        'creadoPor.servidor', 'publicadoPor.servidor', 'retiradoPor.servidor',
    ];

    public function __construct(
        private readonly ComunicadoServiceInterface $comunicados,
        private readonly DestinatariosComunicadoServiceInterface $destinatarios,
    ) {}

    public function index(ListarComunicadosRequest $request): JsonResponse
    {
        $pagina = $this->comunicados->listar($request->validated());

        return ApiResponse::paginadoDe(
            $pagina,
            ComunicadoResource::collection($pagina->getCollection())->resolve($request),
            'Noticias y comunicados.',
        );
    }

    public function show(Comunicado $comunicado): JsonResponse
    {
        return ApiResponse::ok(
            new ComunicadoResource($this->detalle($comunicado)),
            'Detalle de la publicación.',
        );
    }

    public function store(GuardarComunicadoRequest $request): JsonResponse
    {
        $comunicado = $this->comunicados->crear($request->validated(), $request->user());

        return ApiResponse::created(
            new ComunicadoResource($this->detalle($comunicado)),
            'Borrador guardado.',
        );
    }

    public function update(GuardarComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $comunicado = $this->comunicados->actualizar($comunicado, $request->validated());

        return ApiResponse::ok(
            new ComunicadoResource($this->detalle($comunicado)),
            'Borrador guardado.',
        );
    }

    public function destroy(Comunicado $comunicado): JsonResponse
    {
        $this->comunicados->eliminar($comunicado);

        return ApiResponse::noContent('Borrador eliminado.');
    }

    public function publicar(Request $request, Comunicado $comunicado): JsonResponse
    {
        $comunicado = $this->comunicados->publicar($comunicado, $request->user());

        return ApiResponse::ok(
            new ComunicadoResource($this->detalle($comunicado)),
            $comunicado->vigencia() === VigenciaComunicado::PROGRAMADO
                ? 'Publicación programada: el Portal la mostrará el '.$comunicado->publicado_desde->format('d/m/Y').'.'
                : 'Publicado: ya se ve en el Portal.',
        );
    }

    public function retirar(RetirarComunicadoRequest $request, Comunicado $comunicado): JsonResponse
    {
        $comunicado = $this->comunicados->retirar($comunicado, $request->validated('motivo'), $request->user());

        return ApiResponse::ok(
            new ComunicadoResource($this->detalle($comunicado)),
            'Retirado: ya no se ve en el Portal.',
        );
    }

    public function duplicar(Request $request, Comunicado $comunicado): JsonResponse
    {
        $copia = $this->comunicados->duplicar($comunicado, $request->user());

        return ApiResponse::created(
            new ComunicadoResource($this->detalle($copia)),
            'Copia creada como borrador.',
        );
    }

    public function actualizarPublicacion(ActualizarPublicacionRequest $request, Comunicado $comunicado): JsonResponse
    {
        $comunicado = $this->comunicados->actualizarPublicacion($comunicado, $request->validated());

        return ApiResponse::ok(
            new ComunicadoResource($this->detalle($comunicado)),
            'Publicación actualizada.',
        );
    }

    /** Cuántos lo verían, para el aviso del formulario antes de publicar. */
    public function contarDestinatarios(ContarDestinatariosRequest $request): JsonResponse
    {
        $total = $this->destinatarios->contarSeleccion(
            AudienciaComunicado::from($request->validated('audiencia')),
            $request->validated('unidades') ?? [],
            $request->validated('regimenes') ?? [],
        );

        return ApiResponse::ok(['total' => $total], 'Destinatarios contados.');
    }

    private function detalle(Comunicado $comunicado): Comunicado
    {
        $comunicado->load(self::RELACIONES_DETALLE)->loadCount([
            'adjuntos',
            'lecturas as leidos_count',
            'lecturas as confirmados_count' => fn ($q) => $q->whereNotNull('confirmado_at'),
        ]);
        $comunicado->totalDestinatarios = $this->destinatarios->contar($comunicado);

        return $comunicado;
    }
}
