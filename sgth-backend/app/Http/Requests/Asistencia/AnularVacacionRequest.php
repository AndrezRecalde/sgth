<?php

namespace App\Http\Requests\Asistencia;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El motivo de anular una solicitud de vacaciones.
 *
 * Anular deshace algo —en una aprobada, devuelve días al saldo—, así que
 * queda escrito por qué, igual que al rechazar o revertir un permiso.
 */
class AnularVacacionRequest extends FormRequest
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
            'motivo.required' => 'Debe indicar el motivo de la anulación.',
            'motivo.min'      => 'El motivo debe explicar la razón: al menos 5 caracteres.',
            'motivo.max'      => 'El motivo no puede exceder los 500 caracteres.',
        ];
    }
}
