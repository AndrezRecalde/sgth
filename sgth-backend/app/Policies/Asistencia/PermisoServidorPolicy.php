<?php

namespace App\Policies\Asistencia;

use App\Enums\Permiso;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\User;

/**
 * Quién puede ver, imprimir y anular un permiso.
 *
 * Hasta ahora el control de acceso vivía suelto dentro de `index()` y no
 * existía en `show()` ni en `exportar()`: cualquier usuario autenticado podía
 * leer el permiso de cualquier otro —incluido el motivo de uno por enfermedad—
 * y descargar su PDF. Aquí se centraliza, y se apoya en la matriz de permisos
 * que el `RolPermisoSeeder` ya define en vez de en roles escritos a mano.
 *
 * Ojo con el `Gate::before` de `AppServiceProvider`: admin-ti se salta esta
 * policy entera. Es intencional (superusuario técnico), pero significa que
 * ninguna regla de aquí sirve para probar el blindaje con ese rol.
 */
class PermisoServidorPolicy
{
    /**
     * Entrar al listado. El alcance de lo que se ve dentro lo decide el
     * controlador con `alcance()`, no este método.
     */
    public function verAny(User $user): bool
    {
        return $user->can(Permiso::VER_PERMISOS->value);
    }

    public function ver(User $user, PermisoServidor $permiso): bool
    {
        if ($user->can(Permiso::VER_PERMISOS_TODOS->value)) {
            return true;
        }

        if ($this->esPropio($user, $permiso)) {
            return true;
        }

        // Recepción confirma el documento físico contra el folio: para eso
        // tiene que poder abrirlo.
        if ($user->can(Permiso::CONFIRMAR_RECEPCION->value)) {
            return true;
        }

        // Trabajo Social solo valida enfermedad y calamidad, así que solo esos
        // le corresponden.
        if (
            $user->can(Permiso::VALIDAR_TRABAJO_SOCIAL->value)
            && $this->esDeTrabajoSocial($permiso)
        ) {
            return true;
        }

        return $this->mandaEnLaUnidad($user, $permiso);
    }

    /**
     * El PDF es el permiso completo, con firmas y observación. Se imprime bajo
     * la misma regla con la que se lee — antes no comprobaba nada.
     */
    public function exportar(User $user, PermisoServidor $permiso): bool
    {
        return $this->ver($user, $permiso);
    }

    public function anular(User $user, PermisoServidor $permiso): bool
    {
        return $user->can(Permiso::ANULAR_PERMISO->value);
    }

    /**
     * Rechazar el documento físico es trabajo de quien lo recibe.
     *
     * Talento Humano también puede: opera Recepción cuando no hay nadie, igual
     * que ya ocurre con la confirmación.
     */
    public function rechazar(User $user, PermisoServidor $permiso): bool
    {
        return $user->can(Permiso::CONFIRMAR_RECEPCION->value)
            || $user->can(Permiso::ANULAR_PERMISO->value);
    }

    /**
     * Deshacer una confirmación devuelve saldo de vacaciones ya descontado, así
     * que no es una corrección de mostrador: se pide el mismo permiso que
     * anular, que hoy solo tiene admin-uath.
     */
    public function revertir(User $user, PermisoServidor $permiso): bool
    {
        return $user->can(Permiso::ANULAR_PERMISO->value);
    }

    /**
     * ¿Puede leer el motivo, además del permiso?
     *
     * La observación no es un campo más. En un permiso PERSONAL es la vida
     * privada del servidor y en uno por ENFERMEDAD o CALAMIDAD es un dato de
     * salud o un duelo familiar. La regla vieja solo tapaba el motivo de los
     * personales, que es justo el menos sensible de los tres.
     *
     * - El titular siempre ve el suyo.
     * - Talento Humano (`ver-permisos-todos`) lo necesita para el expediente.
     * - Trabajo Social lo necesita para validar enfermedad y calamidad.
     * - Un permiso OFICIAL describe una diligencia institucional, no un asunto
     *   privado: lo ve quien pueda ver el permiso.
     * - Al jefe inmediato, en todo lo demás, se le muestra el permiso sin el
     *   motivo. Le basta para organizar la jornada.
     */
    public function verObservacion(User $user, PermisoServidor $permiso): bool
    {
        if ($this->esPropio($user, $permiso) || $user->can(Permiso::VER_PERMISOS_TODOS->value)) {
            return true;
        }

        if ($this->tipo($permiso) === TipoPermiso::OFICIAL->value) {
            return $this->ver($user, $permiso);
        }

        return $user->can(Permiso::VALIDAR_TRABAJO_SOCIAL->value)
            && $this->esDeTrabajoSocial($permiso);
    }

    // ── Apoyos ───────────────────────────────────────────────────────

    private function esPropio(User $user, PermisoServidor $permiso): bool
    {
        return $user->servidor_id !== null
            && (int) $user->servidor_id === (int) $permiso->servidor_id;
    }

    /**
     * Jefe o director de la unidad donde se registró el permiso.
     *
     * Se compara contra la unidad que quedó grabada en el permiso y no contra
     * la del servidor hoy: si alguien cambia de unidad, su historial sigue
     * perteneciendo a quien era su jefe entonces. Solo se cae a la unidad
     * actual del servidor cuando el permiso nació sin unidad.
     */
    private function mandaEnLaUnidad(User $user, PermisoServidor $permiso): bool
    {
        $unidadDelUsuario = $user->servidor?->unidad_administrativa_id;

        if (! $unidadDelUsuario) {
            return false;
        }

        $esJefe = $user->can(Permiso::VER_ASISTENCIA_UNIDAD->value)
            || (bool) ($user->servidor?->puesto?->es_jefe);

        if (! $esJefe) {
            return false;
        }

        $unidadDelPermiso = $permiso->unidad_administrativa_id
            ?? $permiso->servidor?->unidad_administrativa_id;

        return $unidadDelPermiso !== null
            && (int) $unidadDelPermiso === (int) $unidadDelUsuario;
    }

    private function esDeTrabajoSocial(PermisoServidor $permiso): bool
    {
        return in_array($this->tipo($permiso), [
            TipoPermiso::ENFERMEDAD->value,
            TipoPermiso::CALAMIDAD->value,
        ], true);
    }

    private function tipo(PermisoServidor $permiso): string
    {
        return $permiso->tipo instanceof TipoPermiso
            ? $permiso->tipo->value
            : (string) $permiso->tipo;
    }
}
