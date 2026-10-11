<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * El acta de finiquito de un obrero (fase 4.5): su número en el sistema del
 * Ministerio del Trabajo, su fecha, lo pagado y, si se tiene, el PDF.
 */
class GuardarActaFiniquitoRequest extends FormRequest
{
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'numero'       => 'número del acta',
            'fecha'        => 'fecha del acta',
            'valor_pagado' => 'valor pagado',
            'archivo'      => 'PDF del acta',
        ];
    }

    public function rules(): array
    {
        return [
            'numero'       => ['required', 'string', 'max:100'],
            'fecha'        => ['required', 'date', 'before_or_equal:today'],
            'valor_pagado' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'observacion'  => ['nullable', 'string', 'max:1000'],
            'archivo'      => ['nullable', 'file', 'mimes:pdf', 'max:'.self::MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.mimes' => 'El acta tiene que ser un PDF.',
            'archivo.max'   => 'El PDF no puede pasar de 10 MB.',
        ];
    }
}
