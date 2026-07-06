<?php
namespace App\Http\Controllers\Dispensario;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\RecetaServiceInterface;
use App\Models\Dispensario\RecetaMedica;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class RecetaController extends Controller
{
    public function __construct(
        private readonly RecetaServiceInterface $recetaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = \App\Models\Dispensario\RecetaMedica::with([
            'items',
        ])->orderBy('created_at', 'desc');

        if ($request->filled('consulta_medica_id')) {
            $query->where(
                'consulta_medica_id',
                $request->integer('consulta_medica_id')
            );
        }

        $recetas = $query->get();

        return ApiResponse::ok($recetas);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'consulta_medica_id'             => ['required', 'integer', 'exists:consultas_medicas,id'],
            'fecha_emision'                  => ['required', 'date'],
            'indicaciones_generales'         => ['nullable', 'string', 'max:1000'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.inventario_medicina_id' => ['required', 'integer', 'exists:inventario_medicinas,id'],
            'items.*.cantidad_prescrita'     => ['required', 'integer', 'min:1'],
            'items.*.dosis'                  => ['required', 'string', 'max:100'],
            'items.*.frecuencia'             => ['required', 'string', 'max:100'],
            'items.*.duracion'               => ['required', 'string', 'max:100'],
            'items.*.observaciones'          => ['nullable', 'string', 'max:500'],
        ]);

        $datosReceta = $request->except('items');
        $items       = $request->input('items', []);
        $result      = $this->recetaService->emitirReceta(
            $datosReceta, $items
        );

        return ApiResponse::created($result, 'Receta emitida.');
    }

    public function show(int $id): JsonResponse
    {
        $receta = RecetaMedica::with([
            'items.inventario',
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar',
        ])->findOrFail($id);

        return ApiResponse::ok($receta);
    }

    public function despachar(
        Request $request,
        int $id
    ): JsonResponse {
        $request->validate([
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.item_receta_id' => ['required', 'integer'],
            'items.*.cantidad'       => ['required', 'integer', 'min:1'],
        ]);

        $items  = $request->input('items', []);
        $receta = $this->recetaService->despacharReceta(
            $id, $items, $request->user()->id
        );

        return ApiResponse::ok($receta, 'Receta despachada exitosamente.');
    }
}