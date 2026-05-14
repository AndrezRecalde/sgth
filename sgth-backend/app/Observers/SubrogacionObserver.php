<?php

namespace App\Observers;

use App\Models\Expediente\Subrogacion;

class SubrogacionObserver
{
    public function created(Subrogacion $subrogacion): void
    {
        activity()->performedOn($subrogacion)->log('creado');
    }

    public function updated(Subrogacion $subrogacion): void
    {
        activity()->performedOn($subrogacion)->log('actualizado');
    }
}
