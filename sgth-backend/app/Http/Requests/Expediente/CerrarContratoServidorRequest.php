<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

class CerrarContratoServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo_fin' => 'required|string|max:500',
            'fecha_fin'  => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'motivo_fin.required' => 'Debe indicar el motivo de cierre del contrato.',
        ];
    }
}
