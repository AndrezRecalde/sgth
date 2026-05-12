<?php

namespace App\Observers;

use App\Models\Estructura\Puesto;

class PuestoObserver
{
    public function created(Puesto $puesto): void
    {
        activity()
            ->performedOn($puesto)
            ->event('created')
            ->log('Puesto orgánico creado');
    }

    public function updated(Puesto $puesto): void
    {
        activity()
            ->performedOn($puesto)
            ->event('updated')
            ->log('Puesto orgánico actualizado');
    }

    public function deleted(Puesto $puesto): void
    {
        activity()
            ->performedOn($puesto)
            ->event('deleted')
            ->log('Puesto orgánico eliminado');
    }

    public function restored(Puesto $puesto): void
    {
        activity()
            ->performedOn($puesto)
            ->event('restored')
            ->log('Puesto orgánico restaurado');
    }
}
