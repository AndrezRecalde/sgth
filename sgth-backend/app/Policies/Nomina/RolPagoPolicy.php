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

        // servidores.user_id ya no existe (la FK se invirtió en
        // 2026_05_27_161227_reestructurar_relacion_users_servidores.php):
        // ahora es users.servidor_id el que apunta al Servidor.
        return $rolPago->servidor && $rolPago->servidor->id === $user->servidor_id;
    }
}
