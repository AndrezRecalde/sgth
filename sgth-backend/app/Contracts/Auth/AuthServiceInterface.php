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
     * @param  string  $ip  IP de quien lo intenta, para limitar los fallos
     * @return array Datos de respuesta incluyendo token y flag primer_login
     */
    public function login(string $usuario, string $contrasena, string $ip): array;

    /**
     * Cambiar la contraseña inicial por defecto (cédula).
     *
     * @param  User  $user  El usuario autenticado
     * @param  string  $nuevaContrasena  La nueva contraseña
     */
    public function cambiarContrasenaInicial(User $user, string $nuevaContrasena): void;

    /**
     * Cambiar la contraseña y cerrar las demás sesiones del usuario.
     *
     * @param  User  $user  El usuario autenticado
     * @param  string  $nuevaContrasena  La nueva contraseña, ya validada
     */
    public function cambiarContrasena(User $user, string $nuevaContrasena): void;
}
