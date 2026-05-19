<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlergiaPacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:medicamento,alimento,ambiental,otro'],
            'descripcion' => ['required', 'string', 'max:255'],
            'severidad' => ['required', 'in:leve,moderada,grave'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de alergia es obligatorio.',
            'tipo.in' => 'El tipo de alergia no es válido.',
            'descripcion.required' => 'La descripción de la alergia es obligatoria.',
            'descripcion.max' => 'La descripción no puede exceder los 255 caracteres.',
            'severidad.required' => 'La severidad es obligatoria.',
            'severidad.in' => 'La severidad debe ser leve, moderada o grave.',
        ];
    }
}
