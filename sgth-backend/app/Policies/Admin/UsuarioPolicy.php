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

    /**
     * Vincular o desvincular la ficha de servidor cambia de quién es el
     * expediente que el usuario ve. Es gestión de usuarios, no de estado.
     */
    public function vincularServidor(User $user, User $model): bool
    {
        return $user->can(Permiso::GESTIONAR_USUARIOS->value);
    }

    /**
     * Los permisos directos saltan por encima de los roles, así que quien los
     * otorga puede concederse cualquier privilegio del sistema. Se exige
     * `gestionar-roles`, que en el seeder solo tiene admin-ti: antes bastaba
     * con entrar al grupo de rutas 'admin', y admin-uath —que no puede ni
     * listar usuarios— podía auto-otorgarse 'configurar-sistema'.
     *
     * La prohibición de tocarse a uno mismo NO vive aquí: Gate::before deja
     * pasar a admin-ti antes de consultar cualquier policy. Está en
     * UsuarioService, donde ningún atajo la puede saltar.
     */
    public function gestionarPermisos(User $user, User $model): bool
    {
        return $user->can(Permiso::GESTIONAR_ROLES->value);
    }
}
