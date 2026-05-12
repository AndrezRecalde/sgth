<?php

namespace App\Observers;

use App\Models\Expediente\Servidor;

class ServidorObserver
{
    public function created(Servidor $servidor): void
    {
        activity()->performedOn($servidor)->log('creado');
    }

    public function updated(Servidor $servidor): void
    {
        activity()->performedOn($servidor)->log('actualizado');
    }

    public function deleted(Servidor $servidor): void
    {
        activity()->performedOn($servidor)->log('eliminado');
    }
}
