<?php

namespace App\Policies\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class UnidadAdministrativaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::VER_ESTRUCTURA->value);
    }

    public function view(User $user, UnidadAdministrativa $unidadAdministrativa): bool
    {
        return $user->can(Permiso::VER_ESTRUCTURA->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_ORGANIGRAMA->value);
    }

    public function update(User $user, UnidadAdministrativa $unidadAdministrativa): bool
    {
        return $user->can(Permiso::GESTIONAR_ORGANIGRAMA->value);
    }

    public function delete(User $user, UnidadAdministrativa $unidadAdministrativa): bool
    {
        return $user->can(Permiso::GESTIONAR_ORGANIGRAMA->value);
    }
}
