<?php

namespace App\Policies\Asistencia;

use App\Enums\Permiso;
use App\Models\Asistencia\Vacacion;
use App\Models\Expediente\Servidor;
use App\Models\User;

/**
 * Quién puede ver, registrar y resolver vacaciones, y gestionar sus períodos.
 *
 * Hasta ahora no había ninguna comprobación: cualquier usuario autenticado
 * aprobaba vacaciones ajenas, descargaba el PDF de cualquiera, listaba las de
 * toda la institución y regeneraba los períodos de la plantilla entera. Los
 * permisos `gestionar-vacaciones`, `aprobar-vacaciones` y
 * `ver-vacaciones-unidad` ya estaban sembrados en `RolPermisoSeeder` y nada los
 * consultaba. Aquí se aplican tal como están en la matriz.
 *
 * No existe un `ver-vacaciones-todas`. Ver toda la institución se decide con
 * `ver-asistencia-todos`: las vacaciones son parte de Asistencia, y los roles
 * que lo tienen —Talento Humano, máxima autoridad, auditoría— son justo los que
 * deben verlas enteras.
 *
 * Ojo con el `Gate::before` de `AppServiceProvider`: admin-ti se salta esta
 * policy entera. Por eso la regla de no resolver la propia solicitud vive en el
 * controlador y no aquí.
 */
class VacacionPolicy
{
    /**
     * Entrar al listado. Qué filas se ven dentro lo recorta el controlador.
     */
    public function verAny(User $user): bool
    {
        return $this->alcanceInstitucional($user)
            || $user->can(Permiso::VER_VACACIONES_UNIDAD->value);
    }

    /**
     * ¿Ve las de toda la institución, sin recorte por unidad?
     */
    public function verTodas(User $user): bool
    {
        return $this->alcanceInstitucional($user);
    }

    public function ver(User $user, Vacacion $vacacion): bool
    {
        if ($this->alcanceInstitucional($user) || $this->esPropio($user, $vacacion->servidor_id)) {
            return true;
        }

        // Se mira la unidad grabada en la solicitud y no la del servidor hoy:
        // si alguien cambia de unidad, su historial sigue siendo de su jefe de
        // entonces. Solo se cae a la actual si la solicitud nació sin unidad.
        $unidad = $vacacion->unidad_administrativa_id
            ?? $vacacion->servidor?->unidad_administrativa_id;

        return $this->veLaUnidad($user, $unidad);
    }

    /**
     * El PDF es la solicitud completa: se imprime bajo la regla con la que se lee.
     */
    public function exportar(User $user, Vacacion $vacacion): bool
    {
        return $this->ver($user, $vacacion);
    }

    /**
     * Registrar una solicitud: la propia siempre, la de otro solo si se
     * gestionan vacaciones.
     */
    public function crear(User $user, Servidor $servidor): bool
    {
        return $this->esPropio($user, $servidor->id)
            || $user->can(Permiso::GESTIONAR_VACACIONES->value);
    }

    /**
     * Aprobar o rechazar. Aprobar descuenta días del saldo: no es algo que se
     * pueda conceder por estar en la misma unidad.
     */
    public function resolver(User $user, Vacacion $vacacion): bool
    {
        return $user->can(Permiso::APROBAR_VACACIONES->value);
    }

    /**
     * Saldo y resumen de períodos de un servidor: la misma regla que leer sus
     * vacaciones.
     */
    public function verSaldo(User $user, Servidor $servidor): bool
    {
        return $this->alcanceInstitucional($user)
            || $this->esPropio($user, $servidor->id)
            || $this->veLaUnidad($user, $servidor->unidad_administrativa_id);
    }

    /**
     * Generar, recalcular y previsualizar períodos. Todas cambian —o preparan
     * cambiar— saldos de vacaciones, incluso de años ya cerrados.
     */
    public function gestionarPeriodos(User $user): bool
    {
        return $user->can(Permiso::GESTIONAR_VACACIONES->value);
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    private function alcanceInstitucional(User $user): bool
    {
        return $user->can(Permiso::VER_ASISTENCIA_TODOS->value)
            || $user->can(Permiso::GESTIONAR_VACACIONES->value)
            || $user->can(Permiso::APROBAR_VACACIONES->value);
    }

    private function esPropio(User $user, ?int $servidorId): bool
    {
        return $user->servidor_id !== null
            && $servidorId !== null
            && (int) $user->servidor_id === (int) $servidorId;
    }

    private function veLaUnidad(User $user, ?int $unidadId): bool
    {
        $unidadDelUsuario = $user->servidor?->unidad_administrativa_id;

        return $unidadDelUsuario !== null
            && $unidadId !== null
            && $user->can(Permiso::VER_VACACIONES_UNIDAD->value)
            && (int) $unidadDelUsuario === (int) $unidadId;
    }
}
