<?php

namespace App\Http\Requests\Asistencia;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El motivo de un rechazo o de una reversión de confirmación.
 *
 * Sustituye a `UpdatePermisoServidorRequest`, que validaba un `estado` libre
 * contra el enum completo y nunca se usó: no había ruta que lo recibiera. Un
 * endpoint que acepta cualquier estado deja que la validez de la transición la
 * decida quien llama, y el resto del módulo no funciona así —confirmar, validar
 * y anular son cada uno su propia acción, con su propia regla de origen.
 *
 * Las dos acciones que usan este request deshacen algo, y por eso exigen
 * motivo: queda escrito quién lo hizo y por qué.
 */
class MotivoPermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado en la policy
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe indicar el motivo.',
            'motivo.min'      => 'El motivo debe explicar la razón: al menos 5 caracteres.',
            'motivo.max'      => 'El motivo no puede exceder los 500 caracteres.',
        ];
    }
}
