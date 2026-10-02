<?php

namespace App\Http\Requests\Auth;

use App\Rules\ContrasenaDistintaDeLaActual;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cambio de contraseña por iniciativa propia, con la sesión ya establecida.
 *
 * A diferencia del primer acceso, aquí sí se pide la contraseña actual: es lo
 * único que separa al dueño de la cuenta de quien tenga su token, o de quien
 * se siente frente a un equipo con la sesión abierta.
 */
final class ActualizarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string', 'current_password:sanctum'],
            'nueva_contrasena'  => ContrasenaDistintaDeLaActual::reglas($this->user()),
        ];
    }

    public function messages(): array
    {
        return [
            'contrasena_actual.required'         => 'Ingrese su contraseña actual.',
            'contrasena_actual.current_password' => 'La contraseña actual no es correcta.',
            ...ContrasenaDistintaDeLaActual::mensajes('nueva_contrasena'),
        ];
    }
}
