<?php
namespace App\Http\Controllers\Dispensario;

use App\Enums\EstadoReceta;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreRecetaMedicaRequest;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\RecetaServiceInterface;
use App\Models\Dispensario\RecetaMedica;
use App\Services\Dispensario\PdfRecetaService;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class RecetaController extends Controller
{
    /** Techo del paginador: `?per_page=-1` salía sin LIMIT y traía la tabla entera. */
    private const PER_PAGE_MAX = 100;

    public function __construct(
        private readonly RecetaServiceInterface $recetaService,
        private readonly PdfRecetaService $pdfService
    ) {}

    /** El impreso, para entregárselo al paciente o archivarlo. */
    public function pdf(int $id): Response
    {
        $resultado = $this->pdfService->generarContent($id);

        return response($resultado['content'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'
                . $resultado['filename'] . '"',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->validarFiltros($request);

        $query = RecetaMedica::with([
            // Con el resumen de lotes: quien despacha necesita saber cuánto de
            // ese stock se puede entregar, no cuánto hay en el estante.
            'items.inventario' => fn ($q) => $q->conResumenDeLotes(),
            'consultaMedica.historiaClinica.servidor',
            'consultaMedica.historiaClinica.cargaFamiliar.servidor',
            'consultaMedica.medico:id,usuario_ti,email,servidor_id',
            'consultaMedica.medico.servidor:id,nombre,apellido',
        ])
            ->orderBy('created_at', 'desc')
            // El desempate no es cosmético: `created_at` es `timestamp(0)` y las
            // recetas de una misma consulta caen en el mismo segundo. Con el
            // orden empatado, Postgres resuelve cada página como le conviene y
            // no tienen por qué coincidir entre sí: la segunda repetía filas de
            // la primera, y las que desplazaba no salían en ninguna.
            ->orderBy('id', 'desc');

        $this->aplicarFiltros($query, $request);

        $perPage = min(
            max($request->integer('per_page', 15), 1),
            self::PER_PAGE_MAX
        );

        $recetas = $query->paginate($perPage);

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

    /**
     * Revisa los filtros antes de que lleguen a la consulta.
     *
     * El listado no validaba nada —a diferencia de `store`, que sí— y eso se
     * notaba de dos maneras: `?fecha_desde=hola` viajaba tal cual hasta
     * Postgres y volvía como un 500 («invalid input syntax for type date»), y
     * `?estado=inventado` respondía 200 con la lista vacía, así que quien se
     * equivocaba escribiendo el estado concluía que no había recetas.
     *
     * `estados` llega como lista separada por comas; se normaliza aquí para
     * poder validar cada elemento, y de paso deja de perderse el segundo valor
     * cuando alguien escribe «pendiente, anulada» con el espacio de después de
     * la coma.
     */
    private function validarFiltros(Request $request): void
    {
        if ($request->has('estados')) {
            $crudos = $request->input('estados');

            $request->merge([
                'estados' => array_values(array_filter(array_map(
                    'trim',
                    is_array($crudos) ? $crudos : explode(',', (string) $crudos)
                ), fn ($estado) => $estado !== '')),
            ]);
        }

        $request->validate([
            'consulta_medica_id' => ['sometimes', 'integer'],
            'medico_id'          => ['sometimes', 'integer'],
            'estado'             => ['sometimes', Rule::in(EstadoReceta::valores())],
            'estados'            => ['sometimes', 'array'],
            'estados.*'          => [Rule::in(EstadoReceta::valores())],
            'fecha_desde'        => ['sometimes', 'date'],
            'fecha_hasta'        => ['sometimes', 'date', 'after_or_equal:fecha_desde'],
        ]);
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

        // Ya viene normalizada a lista por validarFiltros().
        if ($request->filled('estados')) {
            $query->whereIn('estado', (array) $request->input('estados'));
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

    public function store(StoreRecetaMedicaRequest $request): JsonResponse
    {
        $datos       = $request->validated();
        $items       = $datos['items'];
        $datosReceta = Arr::except($datos, ['items']);

        $result = $this->recetaService->emitirReceta(
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