<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;

/**
 * La nueva escala de remuneraciones (fase 7.3b): la resolución que la fija, la
 * fecha en que rige —hoy o antes—, el dictamen presupuestario y la RMU nueva
 * de cada grado. La vista previa solo necesita los grados.
 */
class EscalaRemuneracionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $soloGrados = $this->routeIs('estructura.escalas-remuneracion.previsualizar');

        return [
            'instrumento'                 => [$soloGrados ? 'nullable' : 'required', 'string', 'max:120'],
            'fecha_instrumento'           => [$soloGrados ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'rige_desde'                  => [$soloGrados ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'dictamen_presupuestario_ref' => [$soloGrados ? 'nullable' : 'required', 'string', 'max:120'],
            'descripcion'                 => ['nullable', 'string', 'max:2000'],
            'grados'                      => ['required', 'array', 'min:1', 'max:100'],
            'grados.*.grupo_ocupacional_id' => ['required', 'integer', 'distinct', 'exists:grupos_ocupacionales,id'],
            'grados.*.rmu_nueva'          => ['required', 'numeric', 'min:1', 'max:99999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'instrumento'                   => 'resolución',
            'fecha_instrumento'             => 'fecha de la resolución',
            'rige_desde'                    => 'fecha en que rige',
            'dictamen_presupuestario_ref'   => 'dictamen presupuestario',
            'descripcion'                   => 'descripción',
            'grados'                        => 'grados',
            'grados.*.grupo_ocupacional_id' => 'grado',
            'grados.*.rmu_nueva'            => 'RMU nueva',
        ];
    }

    public function messages(): array
    {
        return [
            'rige_desde.before_or_equal' => 'La escala rige hoy o antes: si rige más adelante, se registra el día en que rige.',
        ];
    }
}
