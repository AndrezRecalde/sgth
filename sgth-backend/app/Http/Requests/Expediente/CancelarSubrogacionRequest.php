<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El motivo por el que se cancela una subrogación o un encargo.
 *
 * El tope de 500 es el mismo que pide `MotivoModal` en el frontend, que es el
 * modal con el que se escribe: sin él, el formulario aceptaba un texto que el
 * API no rechazaba pero que tampoco cabía en ninguna pantalla que lo muestre.
 */
class CancelarSubrogacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización la resuelve el controlador contra SubrogacionPolicy.
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
