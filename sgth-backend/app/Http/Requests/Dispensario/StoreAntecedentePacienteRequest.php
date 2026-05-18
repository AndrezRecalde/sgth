<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreAntecedentePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:quirurgico,patologico,traumatico,ginecologico,familiar,otro'],
            'descripcion' => ['required', 'string', 'min:5'],
            'fecha_aproximada' => ['nullable', 'integer', 'min:1900', 'max:' . date('Y')],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'El tipo de antecedente es obligatorio.',
            'tipo.in' => 'El tipo de antecedente no es válido.',
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.min' => 'La descripción debe tener al menos 5 caracteres.',
            'fecha_aproximada.integer' => 'El año aproximado debe ser un número entero.',
            'fecha_aproximada.min' => 'El año aproximado no puede ser menor a 1900.',
            'fecha_aproximada.max' => 'El año aproximado no puede ser mayor al año actual.',
        ];
    }
}
