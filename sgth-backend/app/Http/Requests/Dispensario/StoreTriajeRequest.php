<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreTriajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'presion_arterial_sistolica' => ['required', 'integer', 'min:50', 'max:250'],
            'presion_arterial_diastolica' => ['required', 'integer', 'min:30', 'max:150'],
            'frecuencia_cardiaca' => ['required', 'integer', 'min:30', 'max:200'],
            'frecuencia_respiratoria' => ['required', 'integer', 'min:10', 'max:60'],
            'temperatura' => ['required', 'numeric', 'min:34', 'max:42'],
            'saturacion_oxigeno' => ['required', 'integer', 'min:50', 'max:100'],
            'peso_kg' => ['required', 'numeric', 'min:1', 'max:300'],
            'talla_cm' => ['required', 'integer', 'min:30', 'max:250'],
            'motivo_consulta' => ['required', 'string', 'max:1000'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'max' => 'El campo :attribute no debe ser mayor a :max.',
            'string' => 'El campo :attribute debe ser texto.',
        ];
    }
}
