<?php

namespace App\Policies\Expediente;

use App\Models\User;
use App\Models\Expediente\Servidor;

class ServidorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function verAny(User $user): bool
    {
        return $user->hasRole('admin-uath') || $user->hasRole('super-admin');
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

        return $user->id === $servidor->user_id;
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

        return $user->id === $servidor->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function eliminar(User $user, Servidor $servidor): bool
    {
        return $user->hasRole('super-admin');
    }
}
