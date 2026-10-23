<?php

namespace App\Http\Requests\Dispensario;

use App\Enums\AreaExamen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Un examen nuevo para el catálogo. */
class StoreCatalogoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'area'          => ['required', 'string', Rule::in(AreaExamen::valores())],
            'nombre'        => ['required', 'string', 'max:150',
                Rule::unique('catalogo_examenes', 'nombre')
                    ->where('area', $this->input('area')),
            ],
            'etiqueta_nota' => ['nullable', 'string', 'max:40'],
            'indicaciones'  => ['nullable', 'string', 'max:1000'],
            'sensible'      => ['sometimes', 'boolean'],
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
