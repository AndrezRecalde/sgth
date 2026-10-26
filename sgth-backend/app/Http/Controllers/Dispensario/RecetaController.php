<?php
namespace App\Http\Controllers\Dispensario;

use App\Enums\EstadoReceta;
use App\Enums\MotivoNoEntregado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreRecetaMedicaRequest;
use App\Http\Responses\ApiResponse;
use App\Contracts\Dispensario\RecetaServiceInterface;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\RecetaMedica;
use App\Services\Dispensario\AlcanceClinico;
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

    /**
     * De la consulta, lo que necesita quien despacha: de quién es, quién la
     * firmó y cuándo. Iba entera —motivo, enfermedad actual, examen físico,
     * diagnóstico, plan y notas del médico— a todo el que abría el despacho
     * (docs/plan-roles-y-accesos.md, PR 5). El impreso tampoco lleva el
     * diagnóstico.
     */
    private const DE_LA_CONSULTA = [
        'consultaMedica:id,historia_clinica_id,medico_id,fecha_consulta,especialidad',
        'consultaMedica.historiaClinica:id,servidor_id,carga_familiar_id',
        'consultaMedica.historiaClinica.servidor:id,nombre,apellido,cedula',
        'consultaMedica.historiaClinica.cargaFamiliar:id,nombres,apellidos,cedula,servidor_id',
        'consultaMedica.historiaClinica.cargaFamiliar.servidor:id,nombre,apellido',
        'consultaMedica.medico:id,usuario_ti,email,servidor_id',
        'consultaMedica.medico.servidor:id,nombre,apellido',
    ];

    public function __construct(
        private readonly RecetaServiceInterface $recetaService,
        private readonly PdfRecetaService $pdfService
    ) {}

    /** El impreso, para entregárselo al paciente o archivarlo. */
    public function pdf(Request $request, int $id): Response|JsonResponse
    {
        if (! $this->puedeVer($request, $id)) {
            return $this->ajena();
        }

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
            ...self::DE_LA_CONSULTA,
        ])
            // Por la fecha que la pantalla enseña. Ordenar por `created_at`
            // dejaba el listado desordenado a la vista: una receta emitida el
            // 28 de agosto y registrada el 5 de septiembre salía por encima de
            // otra emitida el 31, sin que nada en la fila lo explicara.
            ->orderBy('fecha_emision', 'desc')
            // El desempate no es cosmético: `fecha_emision` es una fecha sin
            // hora, así que todas las de un mismo día empatan. Con el orden
            // empatado, Postgres resuelve cada página como le conviene y no
            // tienen por qué coincidir entre sí: la segunda repetía filas de la
            // primera, y las que desplazaba no salían en ninguna.
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
        // El médico y el odontólogo, las suyas; quien despacha, todas. Va con
        // los filtros para que el listado y sus contadores digan lo mismo.
        if (! AlcanceClinico::despachaRecetas($request->user())) {
            $query->whereHas('consultaMedica', fn ($q) => $q->where('medico_id', $request->user()->id));
        }

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

        // Por `fecha_emision` y no por `created_at`: la columna que el
        // despacho enseña es la de emisión, y filtrar por otra escondía
        // recetas sin decirlo. Una emitida el 28 de agosto y registrada el 5
        // de septiembre no salía al filtrar agosto, y el mes parecía vacío.
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_emision', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('medico_id')) {
            $query->whereHas('consultaMedica', fn($q) =>
                $q->where('medico_id', $request->integer('medico_id'))
            );
        }
    }

    /**
     * Emite una receta en una consulta de quien la emite.
     *
     * Bastaba con que la consulta existiera: un médico podía emitir una receta
     * en la consulta de un colega, y el impreso salía con el nombre, la cédula
     * y el código ACESS del colega, que es lo que toma el PDF de la consulta.
     * Además, quien la emitía no podía luego corregirla ni anularla, porque
     * «la emitió» el otro. Corregir y anular ya exigían ser el autor.
     */
    public function store(StoreRecetaMedicaRequest $request): JsonResponse
    {
        $datos = $request->validated();

        $consulta = ConsultaMedica::findOrFail($datos['consulta_medica_id']);

        if ($consulta->medico_id !== $request->user()->id) {
            return ApiResponse::error(
                'Solo quien atendió la consulta puede emitir recetas en ella: ' .
                'el impreso sale con su firma.',
                null,
                403
            );
        }

        $items       = $datos['items'];
        $datosReceta = [
            ...Arr::except($datos, ['items']),
            // La columna existía y el alta no la escribía.
            'created_by' => $request->user()->id,
        ];

        $result = $this->recetaService->emitirReceta(
            $datosReceta, $items
        );

        return ApiResponse::created($result, 'Receta emitida.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if (! $this->puedeVer($request, $id)) {
            return $this->ajena();
        }

        $receta = RecetaMedica::with([
            // Con el resumen de lotes: quien despacha necesita saber cuánto de
            // ese stock se puede entregar, no cuánto hay en el estante.
            'items.inventario' => fn ($q) => $q->conResumenDeLotes(),
            ...self::DE_LA_CONSULTA,
        ])->findOrFail($id);

        return ApiResponse::ok($receta);
    }

    /** La suya, o cualquiera si despacha. La que no existe sigue dando 404. */
    private function puedeVer(Request $request, int $id): bool
    {
        if (AlcanceClinico::despachaRecetas($request->user())) {
            return true;
        }

        $medico = RecetaMedica::whereKey($id)
            ->join('consultas_medicas', 'consultas_medicas.id', '=', 'recetas_medicas.consulta_medica_id')
            ->value('consultas_medicas.medico_id');

        return $medico === null || (int) $medico === $request->user()->id;
    }

    private function ajena(): JsonResponse
    {
        return ApiResponse::error('Esta receta es de otro profesional: solo la ve quien la emitió y quien la despacha.', null, 403);
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

    /**
     * Un medicamento del inventario que el mostrador no va a entregar
     * (decisión del Dispensario del 2026-10-24, opción A). Lo marca quien
     * despacha; «otro motivo» pide el detalle.
     */
    public function noEntregado(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'motivo'  => ['required', Rule::in(MotivoNoEntregado::valores())],
            'detalle' => ['nullable', 'string', 'max:255',
                Rule::requiredIf($request->input('motivo') === MotivoNoEntregado::OTRO->value)],
        ], [
            'motivo.in'        => 'Elija el motivo: agotado, vencido u otro.',
            'detalle.required' => 'Explique por qué no se entrega: queda en la receta.',
        ]);

        $receta = $this->recetaService->marcarNoEntregado(
            $id,
            $itemId,
            $request->string('motivo')->value(),
            $request->input('detalle'),
            $request->user()->id,
        );

        $mensaje = $receta->estado === EstadoReceta::CERRADA_CON_FALTANTES->value
            ? 'Marcado como no entregado. La receta se cerró: el impreso dice qué adquirir fuera.'
            : 'Marcado como no entregado: el paciente lo adquiere fuera.';

        return ApiResponse::ok($receta, $mensaje);
    }
}