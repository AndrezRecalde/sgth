<?php

namespace App\Http\Requests\Nomina;

use Illuminate\Foundation\Http\FormRequest;

class StoreNominaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Se maneja en el Policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'periodo'      => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'periodo.required'      => 'El período es obligatorio.',
            'periodo.regex'         => 'El período debe tener el formato YYYY-MM (ej. 2026-05).',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'     => 'La fecha de inicio no es una fecha válida.',
            'fecha_fin.required'    => 'La fecha de fin es obligatoria.',
            'fecha_fin.date'        => 'La fecha de fin no es una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
