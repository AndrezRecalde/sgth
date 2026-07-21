<?php

namespace App\Policies\Sso;

use App\Models\Sso\EquipoProteccion;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Enums\Permiso;

final class EquipoProteccionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::VER_REPORTES_SSO->value) || $user->can(Permiso::GESTIONAR_SSO->value);
    }

    public function view(User $user, EquipoProteccion $model): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_SSO->value);
    }

    public function update(User $user, EquipoProteccion $model): bool
    {
        return $user->can(Permiso::GESTIONAR_SSO->value);
    }

    public function delete(User $user, EquipoProteccion $model): bool
    {
        return $user->can(Permiso::GESTIONAR_SSO->value);
    }
}
