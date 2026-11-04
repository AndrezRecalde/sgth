<?php

namespace App\Policies\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Competencia;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * El diccionario de competencias se lee como el resto de la estructura y lo
 * mantiene quien gestiona los puestos, que es quien arma su perfil.
 */
final class CompetenciaPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::VER_ESTRUCTURA->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_PUESTOS->value);
    }

    public function update(User $user, Competencia $competencia): bool
    {
        return $user->can(Permiso::GESTIONAR_PUESTOS->value);
    }

    public function delete(User $user, Competencia $competencia): bool
    {
        return $user->can(Permiso::GESTIONAR_PUESTOS->value);
    }
}
