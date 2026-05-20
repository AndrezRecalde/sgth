<?php

namespace App\Contracts\Auth;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * Iniciar sesión en el sistema.
     *
     * @param  string  $usuario  Nombre de usuario (usuario_ti)
     * @param  string  $contrasena  Contraseña del usuario
     * @return array Datos de respuesta incluyendo token y flag primer_login
     */
    public function login(string $usuario, string $contrasena): array;

    /**
     * Cambiar la contraseña inicial por defecto (cédula).
     *
     * @param  User  $user  El usuario autenticado
     * @param  string  $nuevaContrasena  La nueva contraseña
     */
    public function cambiarContrasenaInicial(User $user, string $nuevaContrasena): void;
}
