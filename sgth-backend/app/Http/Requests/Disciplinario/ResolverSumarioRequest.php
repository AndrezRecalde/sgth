<?php

namespace App\Http\Requests\Disciplinario;

use App\Enums\TipoFalta;
use App\Enums\TipoSancion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolverSumarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Los topes son los del Art. 43 de la LOSEP, y la cifra que acompaña a la
     * sanción es obligatoria: una multa sin porcentaje o una suspensión sin
     * días dejaban un acto que no decía cuánto, porque las dos columnas eran
     * `nullable` sin condición. `prohibited_unless` cierra el otro lado: no se
     * guardan días de suspensión en una multa.
     */
    public function rules(): array
    {
        return [
            'tipo_falta'       => ['required', Rule::enum(TipoFalta::class)],
            'tipo_sancion'     => ['required', Rule::enum(TipoSancion::class)],
            'porcentaje_multa' => [
                'nullable',
                'required_if:tipo_sancion,'.TipoSancion::MULTA->value,
                'prohibited_unless:tipo_sancion,'.TipoSancion::MULTA->value,
                'numeric',
                'min:0.01',
                'max:10.00',
            ],
            'dias_suspension'  => [
                'nullable',
                'required_if:tipo_sancion,'.TipoSancion::SUSPENSION->value,
                'prohibited_unless:tipo_sancion,'.TipoSancion::SUSPENSION->value,
                'integer',
                'min:1',
                'max:30',
            ],
            'fecha_efectiva'   => ['nullable', 'date'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            // Los nombres de los campos salen de `lang/es/validation.php`; aquí
            // solo lo que cita la norma. El mensaje de `tipo_falta.in` decía
            // «debe ser leve, grave o muy_grave» y enseñaba el valor interno.
            'porcentaje_multa.required_if' => 'Una multa necesita su porcentaje de la remuneración.',
            'porcentaje_multa.max'         => 'La multa no puede exceder el 10% de la remuneración según el Art. 43 de la LOSEP.',
            'dias_suspension.required_if'  => 'Una suspensión necesita sus días.',
            'dias_suspension.max'          => 'La suspensión no puede exceder los 30 días según el Art. 43 de la LOSEP.',
        ];
    }
}
