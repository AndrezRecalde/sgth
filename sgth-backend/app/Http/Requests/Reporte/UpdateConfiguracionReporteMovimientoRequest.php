<?php

namespace App\Http\Requests\Reporte;

use Illuminate\Foundation\Http\FormRequest;

class UpdateConfiguracionReporteMovimientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reportable_siith' => ['sometimes', 'boolean'],
            'reportable_sut'   => ['sometimes', 'boolean'],
            'descripcion'      => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
