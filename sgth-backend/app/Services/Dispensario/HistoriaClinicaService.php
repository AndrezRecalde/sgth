<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Support\Facades\DB;

final class HistoriaClinicaService implements HistoriaClinicaServiceInterface
{
    public function crearHistoria(array $datos): HistoriaClinica
    {
        return HistoriaClinica::create($datos);
    }

    public function registrarConsulta(array $datos): ConsultaMedica
    {
        return DB::transaction(function () use ($datos) {
            $consulta = ConsultaMedica::create($datos);
            
            // Si la consulta atiende una agenda, actualizar el estado de la cita
            if (isset($datos['agenda_medica_id'])) {
                DB::table('agendas_medicas')
                    ->where('id', $datos['agenda_medica_id'])
                    ->update(['estado' => 'atendida']);
            }
            
            return $consulta;
        });
    }
}
