<?php

namespace App\Services\Auth;

use App\Contracts\Auth\AuthServiceInterface;
use App\Exceptions\CuentaDesactivadaException;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthService implements AuthServiceInterface
{
    /**
     * Intentos fallidos sobre una misma cuenta desde un mismo equipo.
     *
     * Solo cuentan los fallos. El límite que había —`throttle:5,1` en la
     * ruta— contaba cada petición por IP, aciertos incluidos: detrás de un
     * proxy o de la NAT de la institución, cinco personas entrando a la vez a
     * primera hora dejaban fuera a la sexta. Y a quien probaba claves de una
     * misma cuenta desde varias IP no lo frenaba.
     */
    private const FALLOS_POR_CUENTA = 5;

    /**
     * Fallos desde una misma IP, sea cual sea la cuenta: frena a quien prueba
     * una clave común contra muchos usuarios. Holgado a propósito, porque
     * toda la institución puede compartir IP.
     */
    private const FALLOS_POR_IP = 30;

    private const VENTANA_SEGUNDOS = 60;

    private static ?string $hashFicticio = null;

    public function login(string $usuario, string $contrasena, string $ip): array
    {
        $claves = $this->clavesDelLimite($usuario, $ip);

        $this->exigirQueNoEsteBloqueado($claves);

        $user = User::where('usuario_ti', $usuario)->first();

        // Con un usuario inexistente se compara igual contra un hash: sin eso
        // la respuesta llegaba en milisegundos en vez de lo que tarda bcrypt,
        // y el tiempo bastaba para saber qué usuarios existen.
        $claveCorrecta = Hash::check($contrasena, $user?->password ?? self::hashFicticio());

        if (!$user || !$claveCorrecta) {
            RateLimiter::hit($claves['cuenta'], self::VENTANA_SEGUNDOS);
            RateLimiter::hit($claves['ip'], self::VENTANA_SEGUNDOS);

            throw new AuthenticationException('Las credenciales proporcionadas son incorrectas.');
        }

        RateLimiter::clear($claves['cuenta']);

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

    /**
     * @return array{cuenta: string, ip: string}
     */
    private function clavesDelLimite(string $usuario, string $ip): array
    {
        $cuenta = Str::lower(Str::transliterate(trim($usuario)));

        return [
            'cuenta' => "login:cuenta:{$cuenta}|{$ip}",
            'ip'     => "login:ip:{$ip}",
        ];
    }

    /**
     * @param  array{cuenta: string, ip: string}  $claves
     */
    private function exigirQueNoEsteBloqueado(array $claves): void
    {
        $limites = [
            $claves['cuenta'] => self::FALLOS_POR_CUENTA,
            $claves['ip']     => self::FALLOS_POR_IP,
        ];

        foreach ($limites as $clave => $maximo) {
            if (RateLimiter::tooManyAttempts($clave, $maximo)) {
                $segundos = RateLimiter::availableIn($clave);

                throw new ThrottleRequestsException(
                    "Demasiados intentos fallidos. Intente nuevamente en {$segundos} segundos.",
                    headers: ['Retry-After' => $segundos],
                );
            }
        }
    }

    private static function hashFicticio(): string
    {
        return self::$hashFicticio ??= Hash::make(Str::random(32));
    }
}
