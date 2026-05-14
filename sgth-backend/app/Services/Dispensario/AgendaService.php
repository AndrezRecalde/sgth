<?php
namespace App\Services\Dispensario;

use App\Contracts\Dispensario\AgendaServiceInterface;
use App\Models\Dispensario\AgendaMedica;
use Illuminate\Support\Facades\DB;

final class AgendaService implements AgendaServiceInterface
{
    public function agendarCita(array $datos): AgendaMedica
    {
        return DB::transaction(function () use ($datos) {
            $cita = AgendaMedica::create($datos);

            // Vinculación Módulo 04: Cita Médica genera permiso por enfermedad automáticamente.
            // Para evitar un acoplamiento estricto a clases, insertamos el permiso directamente en su tabla
            // o a través de un Evento. Aquí lo insertamos como pendiente.
            
            DB::table('permisos_servidor')->insert([
                'servidor_id'      => $cita->servidor_id,
                'tipo'             => 'enfermedad', 
                'fecha'            => $cita->fecha,
                'hora_inicio'      => $cita->hora_inicio,
                'hora_fin'         => $cita->hora_fin,
                'observacion'      => 'Cita médica institucional en el dispensario GAD',
                'estado'           => 'pendiente',
                'vence_en'         => \Carbon\Carbon::parse(substr((string)$cita->fecha, 0, 10) . ' ' . $cita->hora_fin)->addHours(48),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return $cita;
        });
    }
}
