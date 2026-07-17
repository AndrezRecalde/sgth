<?php

namespace App\Http\Requests\Dispensario;

use Illuminate\Foundation\Http\FormRequest;

class AnularOdontogramaProcedimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_anulacion' => ['required', 'string', 'max:255'],
            'consulta_medica_id' => ['nullable', 'integer', 'exists:consultas_medicas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'exists' => 'El registro seleccionado no existe.',
        ];
    }
}
