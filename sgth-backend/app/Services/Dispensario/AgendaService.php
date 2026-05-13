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
            
            DB::table('permisos')->insert([
                'servidor_id'      => $cita->servidor_id,
                'tipo_permiso_id'  => 3, // ID asumido para Permiso por Enfermedad
                'fecha_inicio'     => $cita->fecha . ' ' . $cita->hora_inicio,
                'fecha_fin'        => $cita->fecha . ' ' . $cita->hora_fin,
                'motivo'           => 'Cita médica institucional en el dispensario GAD',
                'estado'           => 'pendiente',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            return $cita;
        });
    }
}
