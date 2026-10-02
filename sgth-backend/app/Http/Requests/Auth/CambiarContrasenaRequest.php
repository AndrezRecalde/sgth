<?php

namespace App\Http\Requests\Auth;

use App\Rules\ContrasenaDistintaDeLaActual;
use Illuminate\Foundation\Http\FormRequest;

final class CambiarContrasenaRequest extends FormRequest
{
    /**
     * Solo mientras la contraseña inicial sigue pendiente de cambio.
     *
     * La ruta no pide la contraseña actual, y estaba abierta a cualquier
     * sesión en cualquier momento: con un token robado bastaba para cambiar la
     * clave del dueño y, de paso, cerrarle todas sus otras sesiones. En el
     * primer acceso pedir la actual no protege nada —es la cédula, que quien
     * tenga el token ya conoce—, pero fuera de él sí. El cambio voluntario va
     * por `ActualizarContrasenaRequest`, que la pide.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->primer_login;
    }

    public function rules(): array
    {
        return [
            'nueva_contrasena' => ContrasenaDistintaDeLaActual::reglas($this->user()),
        ];
    }

    public function messages(): array
    {
        return ContrasenaDistintaDeLaActual::mensajes('nueva_contrasena');
    }
}
