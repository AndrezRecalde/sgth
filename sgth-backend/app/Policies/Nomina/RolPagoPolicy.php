<?php

namespace App\Policies\Nomina;

use App\Enums\EstadoNomina;
use App\Enums\Permiso;
use App\Models\Nomina\RolPago;
use App\Models\User;

class RolPagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permiso::VER_NOMINA_TODAS->value);
    }

    public function view(User $user, RolPago $rolPago): bool
    {
        if ($user->can(Permiso::VER_NOMINA_TODAS->value)) {
            return true;
        }

        // servidores.user_id ya no existe (la FK se invirtió en
        // 2026_05_27_161227_reestructurar_relacion_users_servidores.php):
        // ahora es users.servidor_id el que apunta al Servidor.
        //
        // El propio, solo de una nómina cerrada y que no sea referencial (fase
        // 7.3c): el borrador cambia, y la referencial no es lo que se le pagó.
        $nomina = $rolPago->nomina;

        return $rolPago->servidor && $rolPago->servidor->id === $user->servidor_id
            && $nomina && ! $nomina->referencial
            && in_array($nomina->estado, [EstadoNomina::CERRADA, EstadoNomina::CONTABILIZADA, EstadoNomina::PAGADA], true);
    }
}
