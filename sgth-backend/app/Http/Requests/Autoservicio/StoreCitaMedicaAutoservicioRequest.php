<?php

namespace App\Http\Requests\Autoservicio;

use Illuminate\Foundation\Http\FormRequest;

class StoreCitaMedicaAutoservicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Cualquier servidor autenticado puede solicitar cita
    }

    public function rules(): array
    {
        return [
            'fecha_hora' => ['required', 'date', 'after:now'],
            'sintomas'   => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_hora.required' => 'La fecha y hora de la cita son obligatorias.',
            'fecha_hora.after'    => 'La cita debe ser programada en el futuro.',
            'sintomas.required'   => 'Debe describir brevemente sus síntomas.',
            'sintomas.max'        => 'La descripción de síntomas no puede superar los 500 caracteres.',
        ];
    }
}
