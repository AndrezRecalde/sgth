<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
use App\Models\InventarioTi\AsignacionBien;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class AsignacionBienController extends Controller
{
    public function __construct(
        private readonly InventarioTiServiceInterface $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::ok([], 'Asignaciones listadas');
    }

    public function store(Request $request): JsonResponse
    {
        // Llegaba `$request->all()` sin validar. La custodia se rehace en la
        // fase 1.3; esto es lo mínimo para que el alta no reviente.
        $datos = $request->validate([
            'bien_informatico_id' => ['required', 'integer', 'exists:bienes_informaticos,id'],
            'servidor_id'         => ['required', 'integer', 'exists:servidores,id'],
            'fecha_asignacion'    => ['required', 'date'],
            'observaciones'       => ['nullable', 'string', 'max:2000'],
        ]);

        $asignacion = $this->service->asignarBien($datos);

        // Decía «Asignación creada y Acta PDF generada» y no se generaba
        // ninguna. El acta se pide aparte, y aquí se dice dónde.
        return ApiResponse::created($asignacion, 'Asignación creada');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de asignación');
    }

    /** La devolución del bien (fase 7.4a). */
    public function devolver(Request $request, AsignacionBien $asignacion): JsonResponse
    {
        $datos = $request->validate([
            'fecha_devolucion' => ['required', 'date', 'before_or_equal:today'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],
        ], [], ['fecha_devolucion' => 'fecha de devolución', 'observaciones' => 'observaciones']);

        return ApiResponse::ok(
            $this->service->devolverBien($asignacion, $datos['fecha_devolucion'], $datos['observaciones'] ?? null),
            'Devolución registrada.',
        );
    }

    /**
     * El acta de entrega-recepción, en PDF.
     *
     * Se devuelve `inline`: lo normal es mirarla antes de imprimirla para la
     * firma, que es para lo que existe el documento.
     */
    public function acta(int $id): Response
    {
        $acta = $this->service->generarActaEntrega($id);

        return response($acta['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $acta['filename'] . '"',
        ]);
    }
}
