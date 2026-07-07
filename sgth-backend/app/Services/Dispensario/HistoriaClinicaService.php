<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Contracts\Dispensario\AgendaServiceInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class HistoriaClinicaService implements HistoriaClinicaServiceInterface
{
    public function __construct(
        private readonly AgendaServiceInterface $agendaService
    ) {}
    public function listar(array $filtros): LengthAwarePaginator
    {
        $query = HistoriaClinica::with([
            'servidor', 'cargaFamiliar.servidor',
        ])->orderBy('created_at', 'desc');

        if (!empty($filtros['servidor_id'])) {
            $query->where('servidor_id', $filtros['servidor_id']);
        }

        if (!empty($filtros['carga_familiar_id'])) {
            $query->where(
                'carga_familiar_id', $filtros['carga_familiar_id']
            );
        }

        return $query->paginate($filtros['per_page'] ?? 20);
    }

    public function obtener(int $id): HistoriaClinica
    {
        return HistoriaClinica::with([
            'servidor', 'cargaFamiliar.servidor',
            'alergias' => fn($q) => $q->whereNull('anulado_en'),
            'antecedentes' => fn($q) => $q->whereNull('anulado_en'),
            'consultasMedicas' => fn($q) => $q
                ->orderBy('fecha_consulta', 'desc')
                ->limit(10),
        ])->findOrFail($id);
    }

    public function crearHistoria(array $datos): HistoriaClinica
    {
        $existente = HistoriaClinica::where(
            'servidor_id', $datos['servidor_id'] ?? null
        )->orWhere(
            'carga_familiar_id', $datos['carga_familiar_id'] ?? null
        )->first();

        if ($existente) {
            throw new ReglaNegocioException(
                'Este paciente ya cuenta con una historia ' .
                'clínica registrada.'
            );
        }

        return HistoriaClinica::create($datos);
    }

    public function registrarConsulta(array $datos): ConsultaMedica
    {
        return DB::transaction(function () use ($datos) {
            $secundarios = $datos['diagnosticos_secundarios'] ?? [];
            $datosConsulta = Arr::except(
                $datos, ['diagnosticos_secundarios']
            );

            $consulta = ConsultaMedica::create($datosConsulta);

            foreach ($secundarios as $cie10Id) {
                \App\Models\Dispensario\DiagnosticoSecundarioConsulta::create([
                    'consulta_medica_id'  => $consulta->id,
                    'diagnostico_cie10_id' => $cie10Id,
                ]);
            }

            if (!empty($datos['agenda_medica_id'])) {
                $this->agendaService->marcarAtendido(
                    $datos['agenda_medica_id']
                );
            }

            return $consulta->load([
                'historiaClinica.servidor',
                'historiaClinica.cargaFamiliar',
                'medico',
                'diagnosticosSecundarios.diagnostico',
            ]);
        });
    }

    public function actualizarConsulta(
        int $consultaId,
        array $datos
    ): ConsultaMedica {
        return DB::transaction(function () use (
            $consultaId, $datos
        ) {
            $consulta = ConsultaMedica::findOrFail($consultaId);

            $secundarios = $datos['diagnosticos_secundarios'] ?? null;
            $datosConsulta = Arr::except(
                $datos, ['diagnosticos_secundarios']
            );

            $consulta->update($datosConsulta);

            if ($secundarios !== null) {
                \App\Models\Dispensario\DiagnosticoSecundarioConsulta::where(
                    'consulta_medica_id', $consultaId
                )->delete();

                foreach ($secundarios as $cie10Id) {
                    \App\Models\Dispensario\DiagnosticoSecundarioConsulta::create([
                        'consulta_medica_id'   => $consulta->id,
                        'diagnostico_cie10_id' => $cie10Id,
                    ]);
                }
            }

            return $consulta->load([
                'historiaClinica.servidor',
                'historiaClinica.cargaFamiliar',
                'medico',
                'diagnosticosSecundarios.diagnostico',
            ]);
        });
    }

    public function obtenerContextoConsulta(
        int $historiaClinicaId,
        ?int $agendaMedicaId = null
    ): array {
        $historia = HistoriaClinica::with([
            'servidor',
            'cargaFamiliar.servidor',
            'alergias' => fn($q) => $q->whereNull('anulado_en'),
            'antecedentes' => fn($q) => $q->whereNull('anulado_en'),
        ])->findOrFail($historiaClinicaId);

        $triajeActual = null;
        if ($agendaMedicaId) {
            $triajeActual = \App\Models\Dispensario\Triaje::where(
                'agenda_medica_id', $agendaMedicaId
            )->first();
        }

        $consultasAnteriores = ConsultaMedica::with('medico')
            ->where('historia_clinica_id', $historiaClinicaId)
            ->orderBy('fecha_consulta', 'desc')
            ->limit(3)
            ->get();

        return [
            'historia_clinica'      => $historia,
            'triaje_actual'         => $triajeActual,
            'consultas_anteriores'  => $consultasAnteriores,
        ];
    }
}
