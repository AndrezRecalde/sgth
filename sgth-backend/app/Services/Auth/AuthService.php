<?php

namespace App\Services\Auth;

use App\Contracts\Auth\AuthServiceInterface;
use App\Exceptions\CuentaDesactivadaException;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;

final class AuthService implements AuthServiceInterface
{
    public function login(string $usuario, string $contrasena): array
    {
        $user = User::where('usuario_ti', $usuario)->first();

        if (!$user || !Hash::check($contrasena, $user->password)) {
            throw new AuthenticationException('Las credenciales proporcionadas son incorrectas.');
        }

        // La comprobación va después de validar la contraseña a propósito: así
        // el mensaje de cuenta desactivada solo lo ve quien ya demostró ser el
        // dueño de la cuenta, y no sirve para descubrir qué usuarios existen.
        if (!$user->activo) {
            throw new CuentaDesactivadaException();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'primer_login' => (bool) $user->primer_login,
            'usuario' => $user,
        ];
    }

    public function cambiarContrasenaInicial(User $user, string $nuevaContrasena): void
    {
        $user->password = Hash::make($nuevaContrasena);
        $user->primer_login = false;
        $user->save();
        
        // Revocar todos los tokens excepto el actual (si ya estaba autenticado)
        if ($user->currentAccessToken()) {
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        } else {
            $user->tokens()->delete();
        }
    }
}
