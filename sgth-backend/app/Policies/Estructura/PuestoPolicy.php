<?php

namespace App\Policies\Estructura;

use App\Enums\Permiso;
use App\Enums\Rol;
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

    /**
     * Leer las actividades del puesto. Además de quien ve la estructura, las
     * lee quien evalúa en el Dispensario: son las columnas de la matriz de
     * factores de riesgo (sección G del FEMO), y el médico no tiene
     * `ver-estructura`. Escribirlas es `update`: gestionar el puesto.
     */
    public function verActividades(User $user, Puesto $puesto): bool
    {
        return $user->can(Permiso::VER_ESTRUCTURA->value)
            || $user->hasAnyRole([Rol::MEDICO->value, Rol::ADMIN_DISPENSARIO->value]);
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
