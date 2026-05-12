<?php

namespace App\Policies\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Puesto;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class PuestoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::VER_ESTRUCTURA->value);
    }

    public function view(User $user, Puesto $puesto): bool
    {
        return $user->can(Permiso::VER_ESTRUCTURA->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_PUESTOS->value);
    }

    public function update(User $user, Puesto $puesto): bool
    {
        return $user->can(Permiso::GESTIONAR_PUESTOS->value);
    }

    public function delete(User $user, Puesto $puesto): bool
    {
        return $user->can(Permiso::GESTIONAR_PUESTOS->value);
    }
}
