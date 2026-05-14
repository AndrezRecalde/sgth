<?php

namespace App\Observers\Sso;

use App\Models\Sso\InspeccionSso;

class InspeccionSsoObserver
{
    public function created(InspeccionSso $model): void
    {
        // Auditoría automática
    }

    public function updated(InspeccionSso $model): void
    {
        // Auditoría automática
    }

    public function deleted(InspeccionSso $model): void
    {
        // Auditoría automática
    }
}
