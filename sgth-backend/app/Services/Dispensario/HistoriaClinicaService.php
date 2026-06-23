<?php

namespace App\Services\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Dispensario\ConsultaMedica;
use App\Models\Dispensario\HistoriaClinica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class HistoriaClinicaService implements HistoriaClinicaServiceInterface
{
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
            'alergias', 'antecedentes',
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
            $datosConsulta = Arr::except(
                $datos, ['agenda_medica_id']
            );

            $consulta = ConsultaMedica::create($datosConsulta);

            if (!empty($datos['agenda_medica_id'])) {
                DB::table('agendas_medicas')
                    ->where('id', $datos['agenda_medica_id'])
                    ->update(['estado' => 'atendida']);
            }

            return $consulta->load([
                'historiaClinica.servidor',
                'historiaClinica.cargaFamiliar',
                'medico',
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
            'alergias',
            'antecedentes',
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
