<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSolicitudCertificacionLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servidor_ids' => ['required', 'array', 'min:1'],
            'servidor_ids.*' => ['integer', 'exists:servidores,id'],
            'tipo_evento' => ['required', Rule::in(['periodica', 'reintegro', 'retiro'])],
            'fecha_limite' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'array' => 'El campo :attribute debe ser una lista.',
            'min' => 'Debe seleccionar al menos un servidor.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'Uno o más servidores seleccionados no existen.',
            'in' => 'El tipo de evento seleccionado no es válido.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'string' => 'El campo :attribute debe ser texto.',
        ];
    }
}
