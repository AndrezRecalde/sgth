<?php

namespace App\Observers;

use App\Models\Estructura\UnidadAdministrativa;

class UnidadAdministrativaObserver
{
    public function created(UnidadAdministrativa $unidad): void
    {
        activity()
            ->performedOn($unidad)
            ->event('created')
            ->log('Unidad administrativa creada');
    }

    public function updated(UnidadAdministrativa $unidad): void
    {
        activity()
            ->performedOn($unidad)
            ->event('updated')
            ->log('Unidad administrativa actualizada');
    }

    public function deleted(UnidadAdministrativa $unidad): void
    {
        activity()
            ->performedOn($unidad)
            ->event('deleted')
            ->log('Unidad administrativa eliminada');
    }

    public function restored(UnidadAdministrativa $unidad): void
    {
        activity()
            ->performedOn($unidad)
            ->event('restored')
            ->log('Unidad administrativa restaurada');
    }
}
