<?php

namespace App\Policies\Expediente;

use App\Models\User;
use App\Models\Expediente\Subrogacion;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubrogacionPolicy
{
    use HandlesAuthorization;

    public function registrar(User $user): bool
    {
        return $user->hasRole('admin-uath') || $user->hasRole('super-admin');
    }

    public function finalizar(User $user): bool
    {
        return $user->hasRole('admin-uath') || $user->hasRole('super-admin');
    }

    public function cancelar(User $user): bool
    {
        return $user->hasRole('admin-uath') || $user->hasRole('super-admin');
    }

    public function verAny(User $user): bool
    {
        // Cualquier usuario autenticado puede consultar el listado
        return true;
    }
}
