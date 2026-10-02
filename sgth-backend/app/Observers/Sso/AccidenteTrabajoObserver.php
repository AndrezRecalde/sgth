<?php

namespace App\Observers\Sso;

use App\Models\Sso\AccidenteTrabajo;

/**
 * Rastro de los accidentes de trabajo en `activity_log`.
 *
 * De estos registros salen los tres índices del CD 513 que se reportan al
 * IESS: el tipo de evento decide si cuenta como lesión y los días de reposo
 * son el numerador del índice de gravedad. Cambiar cualquiera de los dos mueve
 * una cifra reportada, y no quedaba rastro de quién lo hizo.
 */
class AccidenteTrabajoObserver
{
    use RegistraActividadSso;

    public function created(AccidenteTrabajo $accidente): void
    {
        $this->registrar($accidente, 'created', 'Accidente de trabajo registrado', $this->datos($accidente));
    }

    public function updated(AccidenteTrabajo $accidente): void
    {
        $this->registrar($accidente, 'updated', 'Accidente de trabajo actualizado', $this->datos($accidente));
    }

    public function deleted(AccidenteTrabajo $accidente): void
    {
        $this->registrar($accidente, 'deleted', 'Accidente de trabajo eliminado', $this->datos($accidente));
    }

    public function restored(AccidenteTrabajo $accidente): void
    {
        $this->registrar($accidente, 'restored', 'Accidente de trabajo restaurado', $this->datos($accidente));
    }

    /** @return array<string, mixed> */
    private function datos(AccidenteTrabajo $accidente): array
    {
        return [
            'servidor_id'        => $accidente->servidor_id,
            // Decide si cuenta como lesión en el índice de frecuencia.
            'tipo_evento'        => $accidente->tipo_evento?->value,
            'gravedad'           => $accidente->gravedad,
            // Numerador del índice de gravedad.
            'dias_reposo_medico' => $accidente->dias_reposo_medico,
            'fecha_accidente'    => $accidente->fecha_accidente?->toDateString(),
        ];
    }
}
