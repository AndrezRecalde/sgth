<?php

namespace App\Observers\Sso;

use App\Models\Sso\InspeccionSso;

/**
 * Rastro de las inspecciones de SSO en `activity_log`.
 *
 * Los hallazgos y las recomendaciones de una inspección son la evidencia que
 * una auditoría del Ministerio del Trabajo viene a pedir, y el conteo de
 * inspecciones es uno de los índices proactivos del módulo. Editar los
 * hallazgos de una inspección ya hecha no dejaba rastro.
 */
class InspeccionSsoObserver
{
    use RegistraActividadSso;

    public function created(InspeccionSso $inspeccion): void
    {
        $this->registrar($inspeccion, 'created', 'Inspección SSO registrada', $this->datos($inspeccion));
    }

    public function updated(InspeccionSso $inspeccion): void
    {
        $this->registrar($inspeccion, 'updated', 'Inspección SSO actualizada', $this->datos($inspeccion));
    }

    public function deleted(InspeccionSso $inspeccion): void
    {
        $this->registrar($inspeccion, 'deleted', 'Inspección SSO eliminada', $this->datos($inspeccion));
    }

    public function restored(InspeccionSso $inspeccion): void
    {
        $this->registrar($inspeccion, 'restored', 'Inspección SSO restaurada', $this->datos($inspeccion));
    }

    /** @return array<string, mixed> */
    private function datos(InspeccionSso $inspeccion): array
    {
        return [
            'unidad_administrativa_id' => $inspeccion->unidad_administrativa_id,
            'tipo_inspeccion'          => $inspeccion->tipo_inspeccion,
            'inspector_id'             => $inspeccion->inspector_id,
            'fecha_inspeccion'         => $inspeccion->fecha_inspeccion?->toDateString(),
        ];
    }
}
