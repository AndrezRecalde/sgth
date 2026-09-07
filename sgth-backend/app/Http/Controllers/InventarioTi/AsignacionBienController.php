<?php
namespace App\Http\Controllers\InventarioTi;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\InventarioTi\InventarioTiServiceInterface;
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
        $asignacion = $this->service->asignarBien($request->all());

        // Decía «Asignación creada y Acta PDF generada» y no se generaba
        // ninguna. El acta se pide aparte, y aquí se dice dónde.
        return ApiResponse::created($asignacion, 'Asignación creada');
    }

    public function show(int $id): JsonResponse
    {
        return ApiResponse::ok(['id' => $id], 'Detalle de asignación');
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
