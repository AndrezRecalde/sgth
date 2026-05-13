<?php

namespace App\Observers\Sso;

use App\Models\Sso\EquipoProteccion;

class EquipoProteccionObserver
{
    public function created(EquipoProteccion $model): void
    {
        // Auditoría automática
    }

    public function updated(EquipoProteccion $model): void
    {
        // Auditoría automática
    }

    public function deleted(EquipoProteccion $model): void
    {
        // Auditoría automática
    }
}
