<?php

namespace App\Http\Requests\Asistencia;

use App\Enums\EstadoPermiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePermisoServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(array_column(EstadoPermiso::cases(), 'value'))],
            'observacion_rechazo' => ['nullable', 'string', 'max:500'], // Opcional si se rechaza
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El nuevo estado del permiso es obligatorio.',
            'estado.in'       => 'El estado proporcionado no es válido dentro del flujo de permisos.',
        ];
    }
}
