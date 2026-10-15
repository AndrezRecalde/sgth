<?php

namespace App\Http\Controllers\Autoservicio;

use App\Enums\Actividades\CategoriaActividadEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Autoservicio\ExportarActividadesRequest;
use App\Http\Requests\Autoservicio\FiltrarActividadesRequest;
use App\Http\Requests\Autoservicio\GuardarActividadRequest;
use App\Http\Resources\Actividades\ActividadLaboralResource;
use App\Http\Responses\ApiResponse;
use App\Services\Actividades\MisActividadesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * «Mis actividades» en el portal: el servidor anota sus actividades laborales
 * por fecha, las filtra y exporta el informe en PDF. Siempre las de la cuenta.
 */
class MisActividadesController extends Controller
{
    public function __construct(private readonly MisActividadesService $actividades) {}

    public function index(FiltrarActividadesRequest $request): JsonResponse
    {
        $pagina = $this->actividades->listar($request->user(), $request->validated());

        return ApiResponse::paginadoDe(
            $pagina,
            ActividadLaboralResource::collection($pagina->items())->resolve($request),
            'Mis actividades.',
        );
    }

    public function store(GuardarActividadRequest $request): JsonResponse
    {
        $actividad = $this->actividades->registrar($request->user(), $request->validated());

        return ApiResponse::ok(
            (new ActividadLaboralResource($actividad))->resolve($request),
            'Actividad anotada.',
            201
        );
    }

    public function update(GuardarActividadRequest $request, int $actividad): JsonResponse
    {
        return ApiResponse::ok(
            (new ActividadLaboralResource($this->actividades->actualizar($request->user(), $actividad, $request->validated())))->resolve($request),
            'Actividad corregida.'
        );
    }

    public function destroy(Request $request, int $actividad): JsonResponse
    {
        $this->actividades->eliminar($request->user(), $actividad);

        return ApiResponse::ok(null, 'Actividad eliminada.');
    }

    /** El informe del rango filtrado, dirigido a su jefe, con las dos firmas. */
    public function pdf(ExportarActividadesRequest $request): Response
    {
        $filtros = $request->validated();
        $informe = $this->actividades->paraInforme($request->user(), $filtros);
        $desde = Carbon::parse($filtros['desde'])->format('Ymd');
        $hasta = Carbon::parse($filtros['hasta'])->format('Ymd');

        $pdf = Pdf::loadView('pdf.actividades.informe-actividades', [
            ...$informe,
            'categoria' => filled($filtros['categoria'] ?? null)
                ? CategoriaActividadEnum::from($filtros['categoria'])->etiqueta()
                : null,
            'buscar'    => $filtros['buscar'] ?? null,
            // La hoja membretada y los datos vigentes hoy (fase 2.2).
            ...app(\App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface::class)->paraDocumento(),
        ])
            ->setPaper('a4');

        // La página, sobre la línea del pie del membrete. Desde aquí y no desde
        // la plantilla: el total de páginas solo se sabe al final, y escribirlo
        // en la vista exigiría activar el PHP de dompdf.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(
            495, 774, 'Página {PAGE_NUM} de {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('helvetica'), 7.5, [0.4, 0.4, 0.4],
        );

        return $pdf->download("actividades_{$informe['servidor']->cedula}_{$desde}_{$hasta}.pdf");
    }
}
