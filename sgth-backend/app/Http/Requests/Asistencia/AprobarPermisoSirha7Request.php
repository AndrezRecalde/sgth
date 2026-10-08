<?php

namespace App\Http\Requests\Asistencia;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El tipo de Sirha7 con que se registra el permiso. Lo elige siempre quien
 * aprueba (decisión del 2026-10-07); que exista lo comprueba el servicio
 * contra la lista de Sirha7.
 */
class AprobarPermisoSirha7Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado en la policy
    }

    public function rules(): array
    {
        return [
            'leave_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function attributes(): array
    {
        return ['leave_id' => 'tipo de permiso de Sirha7'];
    }

    public function messages(): array
    {
        return [
            'leave_id.required' => 'Elija el tipo de permiso con que se registra en Sirha7.',
        ];
    }
}
