<?php

namespace App\Policies\Admin;

use App\Enums\Permiso;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class UsuarioPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_USUARIOS->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permiso::GESTIONAR_USUARIOS->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_USUARIOS->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permiso::GESTIONAR_USUARIOS->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permiso::GESTIONAR_USUARIOS->value);
    }

    public function restablecerContrasena(User $user, User $model): bool
    {
        return $user->can(Permiso::RESTABLECER_CONTRASENA->value);
    }

    public function toggleActivo(User $user, User $model): bool
    {
        return $user->can(Permiso::ACTIVAR_USUARIO->value);
    }
}
