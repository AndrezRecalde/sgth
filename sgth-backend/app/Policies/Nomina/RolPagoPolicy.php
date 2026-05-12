<?php

namespace App\Policies\Nomina;

use App\Models\Nomina\RolPago;
use App\Models\User;

class RolPagoPolicy
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
    public function view(User $user, RolPago $rolPago): bool
    {
        if ($user->hasRole('admin-uath')) {
            return true;
        }

        return $rolPago->servidor && $rolPago->servidor->user_id === $user->id;
    }
}
