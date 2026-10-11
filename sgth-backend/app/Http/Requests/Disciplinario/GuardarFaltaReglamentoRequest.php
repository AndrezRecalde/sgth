<?php

namespace App\Http\Requests\Disciplinario;

use App\Models\Disciplinario\FaltaReglamentoInterno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Una falta del catálogo del reglamento interno (fase 4.4): su artículo, su
 * descripción, las sanciones que admite y el tope de la multa, que nunca pasa
 * del 10 % de la remuneración.
 */
class GuardarFaltaReglamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'articulo'                => 'artículo del reglamento',
            'descripcion'             => 'descripción de la falta',
            'gravedad'                => 'gravedad',
            'porcentaje_maximo_multa' => 'porcentaje máximo de la multa',
        ];
    }

    public function rules(): array
    {
        return [
            'articulo'                    => ['required', 'string', 'max:100'],
            'descripcion'                 => ['required', 'string', 'max:1000'],
            'gravedad'                    => ['nullable', 'string', 'max:40'],
            'admite_amonestacion_verbal'  => ['boolean'],
            'admite_amonestacion_escrita' => ['boolean'],
            'admite_multa'                => ['boolean'],
            'porcentaje_maximo_multa'     => [
                'nullable',
                'required_if_accepted:admite_multa',
                'numeric',
                'min:0.01',
                'max:'.FaltaReglamentoInterno::MULTA_MAXIMA,
            ],
            'activo'                      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'porcentaje_maximo_multa.required_if_accepted' => 'Indique hasta qué porcentaje de la remuneración llega la multa.',
            'porcentaje_maximo_multa.max' => 'La multa de un obrero no puede pasar del 10 % de su remuneración (Código del Trabajo).',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->boolean('admite_amonestacion_verbal')
                    && ! $this->boolean('admite_amonestacion_escrita')
                    && ! $this->boolean('admite_multa')
                ) {
                    $validator->errors()->add(
                        'sanciones',
                        'Indique al menos una sanción que el reglamento admita para esta falta.'
                    );
                }
            },
        ];
    }
}
