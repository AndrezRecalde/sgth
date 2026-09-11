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
     * Marca como falta injustificada los permisos PENDIENTES cuyo plazo venció.
     *
     * Un solo UPDATE condicionado al estado. Antes cargaba los permisos y los
     * guardaba uno a uno: si Recepción confirmaba uno entre la lectura y el
     * guardado, el job escribía «falta injustificada» encima de un permiso ya
     * activo y con el saldo de vacaciones descontado.
     *
     * Postgres bloquea cada fila que actualiza. Si otra transacción la tiene
     * tomada —una confirmación en curso—, espera a que termine y vuelve a
     * evaluar el WHERE: un permiso que se confirmó mientras tanto ya no está
     * pendiente y queda fuera.
     */
    public function handle(): void
    {
        $contador = PermisoServidor::where('estado', EstadoPermiso::PENDIENTE->value)
            ->where('vence_en', '<', now())
            ->update(['estado' => EstadoPermiso::FALTA_INJUSTIFICADA->value]);

        if ($contador > 0) {
            Log::info("VencerPermisosJob ejecutado: {$contador} permisos han sido marcados como falta injustificada tras superar las 72h laborables.");
        }
    }
}
