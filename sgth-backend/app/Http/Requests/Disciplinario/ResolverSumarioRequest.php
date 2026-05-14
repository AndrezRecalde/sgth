<?php

namespace App\Http\Requests\Disciplinario;

use Illuminate\Foundation\Http\FormRequest;

class ResolverSumarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin-uath');
    }

    public function rules(): array
    {
        return [
            'tipo_falta'       => ['required', 'string', 'in:leve,grave,muy_grave'],
            'tipo_sancion'     => ['required', 'string', 'in:amonestacion_verbal,amonestacion_escrita,multa,suspension,destitucion'],
            'porcentaje_multa' => ['nullable', 'numeric', 'min:0.01', 'max:10.00'], // LOSEP max 10%
            'dias_suspension'  => ['nullable', 'integer', 'min:1', 'max:30'], // LOSEP max 30 días
            'fecha_efectiva'   => ['nullable', 'date'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_falta.in'          => 'El tipo de falta debe ser leve, grave o muy_grave.',
            'tipo_sancion.in'        => 'El tipo de sanción no es válido.',
            'porcentaje_multa.max'   => 'La multa no puede exceder el 10% de la remuneración según LOSEP.',
            'dias_suspension.max'    => 'La suspensión no puede exceder los 30 días según LOSEP.',
        ];
    }
}
