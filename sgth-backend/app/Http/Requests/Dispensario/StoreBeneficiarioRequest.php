<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class StoreBeneficiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'tipo_familiar' => ['nullable', 'in:conyuge,hijo,otro'],
            'cedula' => ['nullable', 'regex:/^\d{10}$/'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'genero' => ['nullable', 'in:masculino,femenino,otro'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder los 100 caracteres.',
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.max' => 'El apellido no puede exceder los 100 caracteres.',
            'tipo_familiar.in' => 'El tipo de familiar debe ser conyuge, hijo u otro.',
            'cedula.regex' => 'La cédula debe contener exactamente 10 dígitos numéricos.',
            'fecha_nacimiento.date' => 'La fecha de nacimiento no es válida.',
            'genero.in' => 'El género debe ser masculino, femenino u otro.',
        ];
    }
}
