<?php

namespace App\Jobs\Asistencia;

use App\Enums\EstadoPermiso;
use App\Models\Asistencia\PermisoServidor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VencerPermisosJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     * Busca todos los permisos pendientes cuya fecha límite (vence_en) haya expirado
     * y los marca como falta injustificada.
     */
    public function handle(): void
    {
        $permisosVencidos = PermisoServidor::where('estado', EstadoPermiso::PENDIENTE->value)
            ->where('vence_en', '<', now())
            ->get();

        $contador = 0;

        foreach ($permisosVencidos as $permiso) {
            $permiso->estado = EstadoPermiso::FALTA_INJUSTIFICADA->value;
            $permiso->save();
            $contador++;
        }

        if ($contador > 0) {
            Log::info("VencerPermisosJob ejecutado: {$contador} permisos han sido marcados como falta injustificada tras superar las 72h laborables.");
        }
    }
}
