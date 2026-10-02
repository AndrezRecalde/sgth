<?php

namespace App\Observers\Sso;

use App\Models\Sso\CapacitacionSso;

/**
 * Rastro de las capacitaciones de SSO en `activity_log`.
 *
 * El tema, la fecha y las horas son la evidencia de la capacitación obligatoria
 * en seguridad y salud, y las horas suman en el índice proactivo
 * `horas_capacitacion_total`.
 */
class CapacitacionSsoObserver
{
    use RegistraActividadSso;

    public function created(CapacitacionSso $capacitacion): void
    {
        $this->registrar($capacitacion, 'created', 'Capacitación SSO registrada', $this->datos($capacitacion));
    }

    public function updated(CapacitacionSso $capacitacion): void
    {
        $this->registrar($capacitacion, 'updated', 'Capacitación SSO actualizada', $this->datos($capacitacion));
    }

    public function deleted(CapacitacionSso $capacitacion): void
    {
        $this->registrar($capacitacion, 'deleted', 'Capacitación SSO eliminada', $this->datos($capacitacion));
    }

    public function restored(CapacitacionSso $capacitacion): void
    {
        $this->registrar($capacitacion, 'restored', 'Capacitación SSO restaurada', $this->datos($capacitacion));
    }

    /** @return array<string, mixed> */
    private function datos(CapacitacionSso $capacitacion): array
    {
        return [
            'tema'           => $capacitacion->tema,
            'fecha'          => $capacitacion->fecha?->toDateString(),
            // Suma en `horas_capacitacion_total` de los índices proactivos.
            'duracion_horas' => $capacitacion->duracion_horas,
            'instructor'     => $capacitacion->instructor,
        ];
    }
}
