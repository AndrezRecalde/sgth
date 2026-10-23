<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\AreaExamen;
use App\Models\Dispensario\CatalogoExamen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Corrige un examen del catálogo o lo retira. Todo es opcional: se cambia lo
 * que viene.
 */
class UpdateCatalogoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $examen = CatalogoExamen::find($this->route('examen'));
        $area = $this->input('area', $examen?->area?->value);

        return [
            'area'          => ['sometimes', 'string', Rule::in(AreaExamen::valores())],
            'nombre'        => ['sometimes', 'string', 'max:150',
                Rule::unique('catalogo_examenes', 'nombre')
                    ->where('area', $area)
                    ->ignore($examen?->id),
            ],
            'etiqueta_nota' => ['sometimes', 'nullable', 'string', 'max:40'],
            'indicaciones'  => ['sometimes', 'nullable', 'string', 'max:1000'],
            'sensible'      => ['sometimes', 'boolean'],
            'activo'        => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ese examen ya está en el catálogo, en la misma área.',
        ];
    }

    public function attributes(): array
    {
        return [
            'etiqueta_nota' => 'dato que completa el médico',
        ];
    }
}
