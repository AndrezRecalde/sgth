<?php

namespace App\Policies\Expediente;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Quién administra y quién consulta las subrogaciones y encargos.
 *
 * Hasta el 2026-09-28 `verAny()` devolvía `true` a secas y las rutas no pedían
 * rol: cualquier usuario autenticado —un servidor sin más— listaba todas las
 * subrogaciones de la institución y el historial de cualquier persona, con
 * nombres, puestos, números de resolución y observaciones. Es el mismo descuido
 * que ya se cerró en las cuentas bancarias (2026-09-19) y en el certificado
 * laboral (2026-09-25).
 *
 * Escribir es de UATH. `asistente-uath` entra porque ya registra y hace avanzar
 * Acciones de Personal —y la subrogación nace siendo una de ellas—: dejarlo
 * fuera repetía la contradicción que se corrigió en `ServidorPolicy` el
 * 2026-09-27, donde la ruta le concedía el rol y el policy le respondía 403.
 *
 * `admin-ti` no aparece aquí: lo cubre el `Gate::before` de AppServiceProvider.
 * En el middleware de las rutas sí hay que nombrarlo, porque ese no pasa por
 * las Gates.
 *
 * No hay `super-admin` en ninguna rama: ese rol no está en el enum `Rol` ni lo
 * siembra `RolPermisoSeeder`, así que las tres comprobaciones que lo
 * nombraban nunca pudieron ser verdad.
 */
class SubrogacionPolicy
{
    use HandlesAuthorization;

    /** Quien administra el módulo: registra, finaliza y cancela. */
    private const ROLES_ESCRITURA = ['admin-uath', 'asistente-uath'];

    /**
     * Quien además solo consulta. Auditoría por su función, y la máxima
     * autoridad porque firma los actos que estas subrogaciones respaldan.
     */
    private const ROLES_LECTURA = [
        'admin-uath', 'asistente-uath', 'auditor', 'maxima-autoridad',
    ];

    public function registrar(User $user): bool
    {
        return $user->hasAnyRole(self::ROLES_ESCRITURA);
    }

    public function finalizar(User $user): bool
    {
        return $user->hasAnyRole(self::ROLES_ESCRITURA);
    }

    public function cancelar(User $user): bool
    {
        return $user->hasAnyRole(self::ROLES_ESCRITURA);
    }

    public function verAny(User $user): bool
    {
        return $user->hasAnyRole(self::ROLES_LECTURA);
    }

    /**
     * El historial de un servidor: lo ve quien administra o audita, y el propio
     * interesado. Es su expediente, del mismo modo que `ServidorPolicy::ver()`
     * le deja abrir su propia ficha.
     *
     * La ruta no puede decidir esto —depende de qué servidor se pide—, así que
     * es la única de las seis que no lleva rol en el middleware.
     */
    public function verDeServidor(User $user, int $servidorId): bool
    {
        return $this->verAny($user) || $user->servidor_id === $servidorId;
    }
}
