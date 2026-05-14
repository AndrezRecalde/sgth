<?php

namespace App\Http\Requests\Nomina;

use App\Enums\EstadoDescuentoRecurrente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDescuentoRecurrenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'valor_cuota'           => ['sometimes', 'numeric', 'min:0.01'],
            'numero_cuotas_total'   => ['sometimes', 'integer', 'min:1'],
            'estado'                => ['sometimes', 'string', Rule::in(array_column(EstadoDescuentoRecurrente::cases(), 'value'))],
            'observacion'           => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'valor_cuota.numeric'         => 'El valor de la cuota debe ser numérico.',
            'valor_cuota.min'             => 'El valor de la cuota debe ser mayor a cero.',
            'numero_cuotas_total.integer' => 'El número de cuotas debe ser un número entero.',
            'numero_cuotas_total.min'     => 'Debe programar al menos 1 cuota.',
            'estado.in'                   => 'El estado seleccionado es inválido (debe ser activo, completado o suspendido).',
        ];
    }
}
