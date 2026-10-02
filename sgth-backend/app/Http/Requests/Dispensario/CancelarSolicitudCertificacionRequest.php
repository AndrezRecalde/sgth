<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El motivo por el que se retira una solicitud de certificación médica.
 *
 * El tope de 500 es el mismo que pide `MotivoModal` en el frontend, que es el
 * modal con el que se escribe: sin él, el formulario aceptaba un texto que el
 * API no rechazaba pero que no cabía en la columna.
 */
class CancelarSolicitudCertificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso lo comprueba el controlador.
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
