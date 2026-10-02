<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Cambiar la clave por la misma no es cambiarla.
 *
 * La cédula cumple las reglas de formato si lleva una letra delante, y la
 * contraseña actual —que en el primer acceso es la cédula, salvo que TI la
 * haya fijado a mano— las cumplía tal cual: se podía «cambiar» la clave por
 * ella misma. Vale para el primer acceso y para el cambio voluntario.
 */
final class ContrasenaDistintaDeLaActual implements ValidationRule
{
    /**
     * Las reglas completas de una contraseña nueva, para que las dos rutas que
     * la cambian no se separen.
     *
     * @return array<int, mixed>
     */
    public static function reglas(User $usuario): array
    {
        return [
            'required',
            'string',
            'min:8',
            'regex:/[a-zA-Z]/', // Al menos una letra
            'regex:/[0-9]/',    // Al menos un número
            new self($usuario),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(string $campo): array
    {
        return [
            "{$campo}.required" => 'La nueva contraseña es obligatoria.',
            "{$campo}.min"      => 'La nueva contraseña debe tener al menos 8 caracteres.',
            "{$campo}.regex"    => 'La nueva contraseña debe contener letras y números.',
        ];
    }

    public function __construct(private readonly User $usuario)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }

        $cedula = $this->usuario->servidor?->cedula;

        if ($cedula !== null && str_contains($value, $cedula)) {
            $fail('La nueva contraseña no puede contener su número de cédula.');

            return;
        }

        if (Hash::check($value, $this->usuario->password)) {
            $fail('La nueva contraseña debe ser distinta de la actual.');
        }
    }
}
