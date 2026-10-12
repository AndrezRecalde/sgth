<?php

namespace App\Policies\Nomina;

use App\Enums\Permiso;
use App\Models\Nomina\Nomina;
use App\Models\User;

/**
 * Hasta la fase 7.3c todo pedía el rol admin-uath, y los permisos de nómina
 * —que el seeder ya repartía— no los consultaba nadie: el asistente, que tiene
 * `procesar-nomina`, no podía calcularla.
 */
class NominaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::VER_NOMINA_TODAS->value);
    }

    public function view(User $user, Nomina $nomina): bool
    {
        return $user->can(Permiso::VER_NOMINA_TODAS->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permiso::PROCESAR_NOMINA->value);
    }

    public function update(User $user, Nomina $nomina): bool
    {
        return $user->can(Permiso::PROCESAR_NOMINA->value);
    }

    public function delete(User $user, Nomina $nomina): bool
    {
        return $user->can(Permiso::PROCESAR_NOMINA->value);
    }

    /** Determina si el usuario puede cerrar una nómina. */
    public function cerrar(User $user, Nomina $nomina): bool
    {
        return $user->can(Permiso::CERRAR_NOMINA->value);
    }
}
