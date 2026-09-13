<?php

namespace App\Policies\Viatico;

use App\Enums\Permiso;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoServidor;

/**
 * Quién puede ver, registrar y mover un viático.
 *
 * Hasta ahora solo seis acciones pedían `gestionar-viaticos`. Todo lo demás lo
 * hacía cualquier usuario autenticado: listar los viáticos de todos, cambiar el
 * monto de uno ajeno, crearlo a nombre de otro servidor, cancelarlo, liquidarlo,
 * aprobar vuelos y descargar los PDF, que llevan la cuenta bancaria.
 *
 * Decidido con el usuario:
 * - El servidor solicita, edita y liquida lo suyo. Los acompañantes lo ven.
 * - Financiero opera: `aprobar-viatico` aprueba y rechaza solicitudes y vuelos;
 *   `gestionar-viaticos` entrega el anticipo, marca la comisión, fija el monto y
 *   registra o corrige a nombre de otro; `liquidar-viatico` revisa la
 *   liquidación (la devuelve a corrección o la contabiliza).
 * - Talento Humano consulta con `ver-viaticos-todos`.
 * - El jefe de unidad no ve los de su unidad, por ahora.
 *
 * En qué estado se puede hacer cada cosa no es asunto de esta policy: lo
 * decide el servicio. Y admin-ti se la salta entera por el `Gate::before`.
 */
class ViaticoPolicy
{
    /**
     * Entrar al listado. Lo que se ve dentro lo recorta el controlador con
     * `veTodos()`: sin ese alcance, solo los viáticos propios.
     */
    public function verAny(User $user): bool
    {
        return $user->can(Permiso::SOLICITAR_VIATICO->value) || $this->veTodos($user);
    }

    /** Registrar un viático: el propio con `solicitar-viatico`; el de otro, quien opera. */
    public function crear(User $user, Servidor $servidor): bool
    {
        if ($user->can(Permiso::GESTIONAR_VIATICOS->value)) {
            return true;
        }

        return $user->servidor_id !== null
            && (int) $user->servidor_id === (int) $servidor->id
            && $user->can(Permiso::SOLICITAR_VIATICO->value);
    }

    /** Leerlo y descargar sus PDF. */
    public function ver(User $user, Viatico $viatico): bool
    {
        return $this->veTodos($user)
            || $this->esTitular($user, $viatico)
            || $this->esAcompanante($user, $viatico);
    }

    /**
     * Cambiar los datos, el itinerario, los acompañantes y la liquidación que
     * presenta el servidor. El acompañante no: el viático lo firma el titular.
     */
    public function editar(User $user, Viatico $viatico): bool
    {
        return $this->esTitular($user, $viatico)
            || $user->can(Permiso::GESTIONAR_VIATICOS->value);
    }

    /** El monto lo calcula el sistema; solo quien opera lo fija a mano. */
    public function cambiarMonto(User $user, ?Viatico $viatico = null): bool
    {
        return $user->can(Permiso::GESTIONAR_VIATICOS->value);
    }

    public function cancelar(User $user, Viatico $viatico): bool
    {
        return $this->editar($user, $viatico);
    }

    public function aprobar(User $user, Viatico $viatico): bool
    {
        return $user->can(Permiso::APROBAR_VIATICO->value);
    }

    public function rechazar(User $user, Viatico $viatico): bool
    {
        return $user->can(Permiso::APROBAR_VIATICO->value);
    }

    /** Entregar el anticipo y marcar la comisión y la liquidación pendiente. */
    public function operar(User $user, Viatico $viatico): bool
    {
        return $user->can(Permiso::GESTIONAR_VIATICOS->value);
    }

    /** Devolver la liquidación a corrección o contabilizarla. */
    public function revisarLiquidacion(User $user, Viatico $viatico): bool
    {
        return $user->can(Permiso::LIQUIDAR_VIATICO->value);
    }

    /** Listar, aprobar y rechazar autorizaciones de vuelo. */
    public function autorizarVuelos(User $user): bool
    {
        return $user->can(Permiso::APROBAR_VIATICO->value);
    }

    /**
     * Ve los viáticos de todos quien consulta y quien opera: para aprobar o
     * contabilizar uno hay que poder encontrarlo.
     */
    public function veTodos(User $user): bool
    {
        return $user->can(Permiso::VER_VIATICOS_TODOS->value)
            || $user->can(Permiso::APROBAR_VIATICO->value)
            || $user->can(Permiso::GESTIONAR_VIATICOS->value)
            || $user->can(Permiso::LIQUIDAR_VIATICO->value);
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    private function esTitular(User $user, Viatico $viatico): bool
    {
        return $user->servidor_id !== null
            && (int) $user->servidor_id === (int) $viatico->servidor_id;
    }

    private function esAcompanante(User $user, Viatico $viatico): bool
    {
        return $user->servidor_id !== null
            && ViaticoServidor::where('viatico_id', $viatico->id)
                ->where('servidor_id', $user->servidor_id)
                ->exists();
    }
}
