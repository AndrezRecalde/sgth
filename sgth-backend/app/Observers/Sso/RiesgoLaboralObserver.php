<?php

namespace App\Observers\Sso;

use App\Models\Sso\RiesgoLaboral;

class RiesgoLaboralObserver
{
    public function created(RiesgoLaboral $model): void
    {
        // Auditoría automática gestionada globalmente
    }

    public function updated(RiesgoLaboral $model): void
    {
        // Auditoría automática gestionada globalmente
    }

    public function deleted(RiesgoLaboral $model): void
    {
        // Auditoría automática gestionada globalmente
    }
}
