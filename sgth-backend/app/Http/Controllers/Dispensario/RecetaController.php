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
        $query = RecetaMedica::with([
            // Con el resumen de lotes: quien despacha necesita saber cuánto de
            // ese stock se puede entregar, no cuánto hay en el estante.
            'items.inventario' => fn ($q) => $q->conResumenDeLotes(),
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar.servidor',
            'consultaMedica.medico:id,usuario_ti,email,servidor_id',
            'consultaMedica.medico.servidor:id,nombre,apellido',
        ])->orderBy('created_at', 'desc');

        $this->aplicarFiltros($query, $request);

        $recetas = $query->paginate($request->integer('per_page', 15));

        // Los contadores por estado van aparte porque ya no se pueden sacar de
        // la lista: con la página cargada solo se vería lo que cabe en ella, y
        // las insignias de la cabecera dirían «3 pendientes» cuando hay
        // cuarenta. Se cuentan sobre los mismos filtros, en una sola consulta.
        $resumen = $this->contarPorEstado($request);

        return ApiResponse::ok(
            $recetas,
            'Listado de recetas.',
            200,
            ['resumen' => $resumen]
        );
    }

    /** @return array<string,int> Cuántas recetas hay de cada estado. */
    private function contarPorEstado(Request $request): array
    {
        $query = RecetaMedica::query();

        $this->aplicarFiltros($query, $request);

        return $query->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function aplicarFiltros(
        \Illuminate\Database\Eloquent\Builder $query,
        Request $request
    ): void {
        if ($request->filled('consulta_medica_id')) {
            $query->where(
                'consulta_medica_id',
                $request->integer('consulta_medica_id')
            );
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('estados')) {
            $query->whereIn('estado', explode(',', $request->input('estados')));
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('medico_id')) {
            $query->whereHas('consultaMedica', fn($q) =>
                $q->where('medico_id', $request->integer('medico_id'))
            );
        }
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
            // Con el resumen de lotes: quien despacha necesita saber cuánto de
            // ese stock se puede entregar, no cuánto hay en el estante.
            'items.inventario' => fn ($q) => $q->conResumenDeLotes(),
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar',
        ])->findOrFail($id);

        return ApiResponse::ok($receta);
    }

    /**
     * Anula una receta para que no se entregue lo que falta. La anula quien la
     * emitió —siguiendo la misma regla que el odontograma— o la administración
     * del dispensario, que es quien atiende el mostrador cuando el paciente
     * ya no vuelve.
     */
    public function anular(
        Request $request,
        int $id
    ): JsonResponse {
        $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:255'],
        ]);

        $receta = RecetaMedica::with('consultaMedica')->findOrFail($id);

        $esAdministracion = $request->user()->hasRole('admin-dispensario');
        $laEmitio = $receta->consultaMedica?->medico_id === $request->user()->id;

        if (! $esAdministracion && ! $laEmitio) {
            return ApiResponse::error(
                'Solo quien emitió la receta puede anularla.', null, 403
            );
        }

        $anulada = $this->recetaService->anularReceta(
            $id,
            $request->string('motivo_anulacion')->value(),
            $request->user()->id
        );

        return ApiResponse::ok($anulada, 'Receta anulada correctamente.');
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