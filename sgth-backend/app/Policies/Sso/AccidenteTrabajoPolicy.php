<?php

namespace App\Policies\Sso;

use App\Models\Sso\AccidenteTrabajo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Enums\Permiso;

final class AccidenteTrabajoPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::VER_REPORTES_SSO->value) || $user->can(Permiso::GESTIONAR_SSO->value);
    }

    public function view(User $user, AccidenteTrabajo $model): bool
    {
        if ($user->servidor_id === $model->servidor_id) {
            return true;
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::REGISTRAR_ACCIDENTE->value) || $user->can(Permiso::GESTIONAR_SSO->value);
    }

    public function update(User $user, AccidenteTrabajo $model): bool
    {
        return $user->can(Permiso::GESTIONAR_SSO->value);
    }
}
