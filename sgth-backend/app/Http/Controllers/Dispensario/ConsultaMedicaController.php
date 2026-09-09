<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Enums\EspecialidadAtencion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreConsultaMedicaRequest;
use App\Http\Requests\Dispensario\UpdateConsultaMedicaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ConsultaMedicaController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface $historiaService
    ) {}

    /** Techo del paginador, el mismo que aplica el listado de recetas. */
    private const PER_PAGE_MAX = 100;

    /**
     * Cuántas consultas trae una página.
     *
     * El valor iba directo a `paginate()`, y `limit()` de Laravel ignora los
     * negativos: `?per_page=-1` salía sin LIMIT y devolvía la tabla entera de
     * una vez, con las notas clínicas de todos los pacientes descifradas en la
     * misma respuesta.
     */
    private function porPagina(Request $request): int
    {
        return min(
            max($request->integer('per_page', 20), 1),
            self::PER_PAGE_MAX
        );
    }

    /**
     * Revisa los filtros antes de que lleguen a la consulta.
     *
     * El listado no validaba nada —a diferencia de `store`, que sí— y eso se
     * notaba de dos maneras, las mismas que ya se corrigieron en el listado de
     * recetas: `?fecha_desde=hola` viajaba tal cual hasta Postgres y volvía
     * como un 500 («invalid input syntax for type date»), y
     * `?especialidad=inventado` respondía 200 con la lista vacía, así que
     * quien se equivocaba escribiendo concluía que el paciente no tenía
     * consultas. En una historia clínica eso es lo peor que se puede decir.
     */
    private function validarFiltros(Request $request): void
    {
        $request->validate([
            'historia_clinica_id' => ['sometimes', 'integer'],
            'medico_id'           => ['sometimes', 'integer'],
            'especialidad'        => ['sometimes', Rule::enum(EspecialidadAtencion::class)],
            'fecha_desde'         => ['sometimes', 'date'],
            'fecha_hasta'         => ['sometimes', 'date', 'after_or_equal:fecha_desde'],
            'per_page'            => ['sometimes', 'integer'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->validarFiltros($request);

        $query = ConsultaMedica::with([
            'historiaClinica.servidor',
            'historiaClinica.cargaFamiliar',
            'medico',
            'recetasMedicas',
            'diagnosticoCie10Principal',
            'diagnosticosSecundarios.diagnostico',
        ])
            ->orderBy('fecha_consulta', 'desc')
            // La hora ordena dentro del día, y el id desempata lo que quede.
            // Sin esto el orden era solo por `fecha_consulta`, que es un `date`:
            // las consultas de un mismo día empataban y Postgres repartía las
            // páginas como le convenía, sin tener por qué coincidir entre sí.
            // El historial de un paciente con varias atenciones el mismo día
            // repetía unas y se saltaba otras al pasar de página.
            ->orderBy('hora_consulta', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('historia_clinica_id')) {
            $query->where(
                'historia_clinica_id',
                $request->historia_clinica_id
            );
        }

        if ($request->filled('medico_id')) {
            $query->where('medico_id', $request->medico_id);
        }

        // Ahora que la consulta sabe de qué especialidad es, se puede pedir
        // solo la de una: era la pregunta que no se podía responder.
        if ($request->filled('especialidad')) {
            $query->where('especialidad', $request->input('especialidad'));
        }

        // El historial de un paciente crece por años. Sin poder acotarlo por
        // fechas hay que pasearse por todas las páginas para llegar al episodio
        // que se busca.
        if ($request->filled('fecha_desde')) {
            $query->whereDate(
                'fecha_consulta', '>=', $request->input('fecha_desde')
            );
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate(
                'fecha_consulta', '<=', $request->input('fecha_hasta')
            );
        }

        $consultas = $query->paginate($this->porPagina($request));

        return ApiResponse::ok($consultas, 'Listado de consultas.');
    }

    public function store(
        StoreConsultaMedicaRequest $request
    ): JsonResponse {
        $datos = [
            ...$request->validated(),
            'medico_id'  => $request->user()->id,
            'created_by' => $request->user()->id,
        ];

        $consulta = $this->historiaService->registrarConsulta(
            $datos
        );

        return ApiResponse::created(
            $consulta, 'Consulta registrada.'
        );
    }

    /**
     * Ventana durante la que su autor puede corregir la nota.
     *
     * Un día es lo que ya aplica el odontograma para anular un procedimiento
     * fuera de su consulta, y es lo que separa «me equivoqué escribiendo» de
     * «estoy reescribiendo el pasado». Pasado ese plazo la corrección deja de
     * ser una corrección: lo que corresponde es una consulta nueva, que es
     * como se rectifica una historia clínica.
     */
    public const HORAS_PARA_CORREGIR = 24;

    public function update(
        UpdateConsultaMedicaRequest $request,
        int $id
    ): JsonResponse {
        $consulta = ConsultaMedica::findOrFail($id);
        $usuario  = $request->user();

        // La nota la firma quien atendió. Hasta ahora cualquier médico podía
        // reescribir la consulta de cualquier colega y de cualquier paciente,
        // sin dejar rastro; el odontograma de la pestaña de al lado ya exigía
        // ser quien registró el procedimiento.
        if ($consulta->medico_id !== $usuario->id) {
            return ApiResponse::error(
                'Solo quien atendió la consulta puede corregirla. Para ' .
                'añadir algo a la historia del paciente, registre una ' .
                'consulta nueva.',
                null,
                403
            );
        }

        if ($consulta->created_at->diffInHours(now()) >= self::HORAS_PARA_CORREGIR) {
            return ApiResponse::error(
                'Esta consulta ya no se puede corregir: se registró hace más ' .
                'de ' . self::HORAS_PARA_CORREGIR . ' horas. Lo que ' .
                'corresponde es registrar una consulta nueva.',
                null,
                422
            );
        }

        $consulta = $this->historiaService->actualizarConsulta(
            $id,
            $request->validated(),
            $usuario->id
        );

        return ApiResponse::ok(
            $consulta, 'Consulta actualizada.'
        );
    }

    /**
     * Lo que la consulta decía antes de cada corrección, de la más reciente a
     * la más antigua. Vacío mientras nadie la haya tocado.
     */
    public function versiones(int $id): JsonResponse
    {
        $consulta = ConsultaMedica::with([
            'versiones.autorDelCambio:id,usuario_ti,email,servidor_id',
            'versiones.autorDelCambio.servidor:id,nombre,apellido',
            'versiones.diagnosticoCie10',
        ])->findOrFail($id);

        return ApiResponse::ok($consulta->versiones);
    }

    public function show(int $id): JsonResponse
    {
        $consulta = ConsultaMedica::with([
            'historiaClinica.servidor',
            'historiaClinica.cargaFamiliar',
            'historiaClinica.alergias',
            'historiaClinica.antecedentes',
            'medico',
            'recetasMedicas.items.inventario',
            'diagnosticoCie10Principal',
            'diagnosticosSecundarios.diagnostico',
            'resultados',
        ])->findOrFail($id);

        return ApiResponse::ok($consulta);
    }
}