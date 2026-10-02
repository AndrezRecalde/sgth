<?php

namespace App\Observers\Sso;

use App\Models\Sso\RiesgoLaboral;

/**
 * Rastro de la matriz de riesgos en `activity_log`.
 *
 * Es el registro más sensible del módulo: el nivel de intervención sale de la
 * NTP 330 y es lo que un auditor de SSO viene a revisar. Quién lo valoró y
 * quién cambió esa valoración después no estaba en ninguna parte.
 */
class RiesgoLaboralObserver
{
    use RegistraActividadSso;

    public function created(RiesgoLaboral $riesgo): void
    {
        $this->registrar($riesgo, 'created', 'Riesgo laboral identificado', $this->datos($riesgo));
    }

    public function updated(RiesgoLaboral $riesgo): void
    {
        $this->registrar($riesgo, 'updated', 'Riesgo laboral actualizado', $this->datos($riesgo));
    }

    public function deleted(RiesgoLaboral $riesgo): void
    {
        $this->registrar($riesgo, 'deleted', 'Riesgo laboral eliminado', $this->datos($riesgo));
    }

    public function restored(RiesgoLaboral $riesgo): void
    {
        $this->registrar($riesgo, 'restored', 'Riesgo laboral restaurado', $this->datos($riesgo));
    }

    /** @return array<string, mixed> */
    private function datos(RiesgoLaboral $riesgo): array
    {
        return [
            'puesto_id'          => $riesgo->puesto_id,
            'factor_riesgo_id'   => $riesgo->factor_riesgo_id,
            // La valoración, que es la cifra con consecuencia.
            'nivel_intervencion' => $riesgo->nivel_intervencion?->value,
            'nivel_riesgo_valor' => $riesgo->nivel_riesgo_valor,
        ];
    }
}
