<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\HistoriaClinicaServiceInterface;
use App\Models\Dispensario\HistoriaClinica;
use App\Models\Dispensario\ConsultaMedica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;

final class HistoriaClinicaService implements HistoriaClinicaServiceInterface
{
    public function crearHistoria(array $datos): HistoriaClinica
    {
        return HistoriaClinica::create($datos);
    }

    public function registrarConsulta(array $datos): ConsultaMedica
    {
        return DB::transaction(function () use ($datos) {
            $datosConsulta = Arr::except($datos, ['agenda_medica_id']);
            $consulta = ConsultaMedica::create($datosConsulta);
            
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
