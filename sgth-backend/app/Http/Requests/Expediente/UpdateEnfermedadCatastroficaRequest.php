<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEnfermedadCatastroficaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_enfermedad'     => 'sometimes|required|string|max:255',
            'codigo_cie10'        => 'nullable|string|max:50',
            'fecha_diagnostico'   => 'nullable|date|before_or_equal:today',
            'archivo_certificado' => 'nullable|file|mimes:pdf|max:5120',
        ];
    }
}
