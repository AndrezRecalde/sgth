<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

final class CambiarContrasenaRequest extends FormRequest
{
    /**
     * Solo mientras la contraseña inicial sigue pendiente de cambio.
     *
     * La ruta no pide la contraseña actual, y estaba abierta a cualquier
     * sesión en cualquier momento: con un token robado bastaba para cambiar la
     * clave del dueño y, de paso, cerrarle todas sus otras sesiones. En el
     * primer acceso pedir la actual no protege nada —es la cédula, que quien
     * tenga el token ya conoce—, pero fuera de él sí. Un cambio voluntario
     * tendrá su propia ruta, con la contraseña actual.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->primer_login;
    }

    public function rules(): array
    {
        return [
            'nueva_contrasena' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-zA-Z]/', // Al menos una letra
                'regex:/[0-9]/',    // Al menos un número
                $this->distintaDeLaInicial(...),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nueva_contrasena.required' => 'La nueva contraseña es obligatoria.',
            'nueva_contrasena.min'      => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'nueva_contrasena.regex'    => 'La nueva contraseña debe contener letras y números.',
        ];
    }

    /**
     * Cambiar la clave por la misma no es cambiarla.
     *
     * La cédula cumple las reglas de formato si lleva una letra delante, y la
     * contraseña actual —que en el primer acceso es la cédula, salvo que TI la
     * haya fijado a mano— las cumplía tal cual: se podía «cambiar» la clave
     * inicial por ella misma y dar por resuelto el primer acceso.
     */
    private function distintaDeLaInicial(string $atributo, mixed $valor, Closure $fallar): void
    {
        if (!is_string($valor)) {
            return;
        }

        $usuario = $this->user();
        $cedula  = $usuario->servidor?->cedula;

        if ($cedula !== null && str_contains($valor, $cedula)) {
            $fallar('La nueva contraseña no puede contener su número de cédula.');

            return;
        }

        if (Hash::check($valor, $usuario->password)) {
            $fallar('La nueva contraseña debe ser distinta de la actual.');
        }
    }
}
