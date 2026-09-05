<?php

namespace App\Http\Controllers\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispensario\StoreConsultaMedicaRequest;
use App\Http\Requests\Dispensario\UpdateConsultaMedicaRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConsultaMedicaController extends Controller
{
    public function __construct(
        private readonly HistoriaClinicaServiceInterface $historiaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ConsultaMedica::with([
            'historiaClinica.servidor',
            'historiaClinica.cargaFamiliar',
            'medico',
            'recetasMedicas',
            'diagnosticoCie10Principal',
            'diagnosticosSecundarios.diagnostico',
        ])->orderBy('fecha_consulta', 'desc');

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

        $consultas = $query->paginate(
            $request->integer('per_page', 20)
        );

        return ApiResponse::ok($consultas, 'Listado de consultas.');
    }

    public function store(
        StoreConsultaMedicaRequest $request
    ): JsonResponse {
        $datos = [
            ...$request->validated(),
            'medico_id'  => $request->user()->id,
            'estado'     => true,
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

    public function marcarEnConsulta(
        Request $request,
        int $id
    ): JsonResponse {
        $agenda = \App\Models\Dispensario\AgendaMedica::findOrFail($id);
        $agenda->update(['estado' => 'en_consulta']);
        return ApiResponse::ok($agenda, 'Turno en consulta.');
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