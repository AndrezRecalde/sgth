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

    public function update(
        UpdateConsultaMedicaRequest $request,
        int $id
    ): JsonResponse {
        $consulta = $this->historiaService->actualizarConsulta(
            $id,
            $request->validated()
        );

        return ApiResponse::ok(
            $consulta, 'Consulta actualizada.'
        );
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