<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/** El salario básico unificado de un año (fase 4.6). */
class GuardarSalarioBasicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'anio'    => 'año',
            'monto'   => 'salario básico',
            'acuerdo' => 'acuerdo ministerial',
        ];
    }

    public function rules(): array
    {
        return [
            'anio'    => ['required', 'integer', 'min:2000', 'max:2100'],
            'monto'   => ['required', 'numeric', 'gt:0', 'max:99999'],
            'acuerdo' => ['nullable', 'string', 'max:150'],
        ];
    }
}
