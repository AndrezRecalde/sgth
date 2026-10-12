<?php

namespace App\Policies\Asistencia;

use App\Enums\CausaLicencia;
use App\Enums\Permiso;
use App\Models\Asistencia\Licencia;
use App\Models\User;

/**
 * Quién ve, registra, aprueba y anula las licencias con remuneración (fase
 * 5.1).
 *
 * - Las registra y las anula Talento Humano (`gestionar-licencias`).
 * - Las aprueba quien aprueba esa causa: Trabajo Social las de salud y la
 *   calamidad, Talento Humano las demás (`CausaLicencia::permisoParaAprobar`).
 * - Las ve quien registra o aprueba alguna; quien solo aprueba algunas causas
 *   ve solo esas (`causasVisibles`).
 *
 * Y admin-ti se salta esta policy entera (Gate::before).
 */
class LicenciaPolicy
{
    public function verAny(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_LICENCIAS->value)
            || $user->can(Permiso::APROBAR_PERMISO_SIRHA7->value)
            || $user->can(Permiso::VALIDAR_TRABAJO_SOCIAL->value);
    }

    public function ver(User $user, Licencia $licencia): bool
    {
        return $user->can(Permiso::GESTIONAR_LICENCIAS->value) || $this->aprobar($user, $licencia);
    }

    public function registrar(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_LICENCIAS->value);
    }

    public function aprobar(User $user, Licencia $licencia): bool
    {
        return $user->can($licencia->causa->permisoParaAprobar()->value);
    }

    public function anular(User $user, Licencia $licencia): bool
    {
        return $user->can(Permiso::GESTIONAR_LICENCIAS->value);
    }

    /**
     * Las causas que puede ver quien no registra licencias: las que aprueba.
     * Null si las ve todas.
     *
     * @return list<string>|null
     */
    public static function causasVisibles(User $user): ?array
    {
        if ($user->can(Permiso::GESTIONAR_LICENCIAS->value)) {
            return null;
        }

        return array_values(array_map(
            fn (CausaLicencia $c) => $c->value,
            array_filter(CausaLicencia::cases(), fn (CausaLicencia $c) => $user->can($c->permisoParaAprobar()->value)),
        ));
    }
}
