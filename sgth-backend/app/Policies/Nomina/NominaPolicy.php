<?php

namespace App\Policies\Nomina;

use App\Models\Nomina\Nomina;
use App\Models\User;

class NominaPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin-uath');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Nomina $nomina): bool
    {
        return $user->hasRole('admin-uath');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin-uath');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Nomina $nomina): bool
    {
        return $user->hasRole('admin-uath');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Nomina $nomina): bool
    {
        return $user->hasRole('admin-uath');
    }

    /**
     * Determina si el usuario puede cerrar una nómina.
     */
    public function cerrar(User $user, Nomina $nomina): bool
    {
        return $user->hasRole('admin-uath');
    }
}
