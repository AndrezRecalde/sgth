<?php

namespace App\Observers\Sso;

use App\Models\Sso\AccidenteTrabajo;

class AccidenteTrabajoObserver
{
    public function created(AccidenteTrabajo $model): void
    {
        // Auditoría automática
    }

    public function updated(AccidenteTrabajo $model): void
    {
        // Auditoría automática
    }

    public function deleted(AccidenteTrabajo $model): void
    {
        // Auditoría automática
    }
}
