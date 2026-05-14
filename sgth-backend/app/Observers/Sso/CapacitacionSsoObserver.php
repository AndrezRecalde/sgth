<?php

namespace App\Observers\Sso;

use App\Models\Sso\CapacitacionSso;

class CapacitacionSsoObserver
{
    public function created(CapacitacionSso $model): void
    {
        // Auditoría automática
    }

    public function updated(CapacitacionSso $model): void
    {
        // Auditoría automática
    }

    public function deleted(CapacitacionSso $model): void
    {
        // Auditoría automática
    }
}
