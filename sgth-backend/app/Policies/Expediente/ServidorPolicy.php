<?php

namespace App\Policies\Expediente;

use App\Models\User;
use App\Models\Expediente\Servidor;

class ServidorPolicy
{
    /**
     * Listar los servidores de la institución.
     *
     * asistente-uath registra permisos a nombre de cualquier servidor
     * (`registrar-permisos-servidores`), y para eso el formulario lista los
     * servidores de la unidad elegida. Sin él aquí, ese selector le respondía
     * 403 y no podía elegir a nadie.
     *
     * Se agrega el rol y no `ver-expediente-todos`, que también tienen
     * analista-uath, maxima-autoridad y auditor: abrirlo por ese permiso
     * habría ampliado el listado a más roles de los que lo necesitan.
     */
    public function verAny(User $user): bool
    {
        return $user->hasRole('admin-uath')
            || $user->hasRole('asistente-uath')
            || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function ver(User $user, Servidor $servidor): bool
    {
        // Un servidor solo puede ver su propio expediente. UATH puede ver todos.
        if ($user->hasRole('admin-uath') || $user->hasRole('super-admin')) {
            return true;
        }

        // servidores.user_id ya no existe (la FK se invirtió en
        // 2026_05_27_161227_reestructurar_relacion_users_servidores.php):
        // ahora es users.servidor_id el que apunta al Servidor.
        return $user->servidor_id === $servidor->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function crear(User $user): bool
    {
        return $user->hasRole('admin-uath') || $user->hasRole('super-admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function actualizar(User $user, Servidor $servidor): bool
    {
        // El servidor titular puede actualizar partes no sensibles (ej. teléfono),
        // pero UATH puede actualizar todo. La validación de campos se delega al Request/Service.
        // Aquí validamos el acceso general a la actualización.
        if ($user->hasRole('admin-uath') || $user->hasRole('super-admin')) {
            return true;
        }

        return $user->servidor_id === $servidor->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function eliminar(User $user, Servidor $servidor): bool
    {
        return $user->hasRole('super-admin');
    }
}
