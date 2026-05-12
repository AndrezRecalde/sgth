<?php

namespace App\Http\Requests\Estructura;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Foundation\Http\FormRequest;

final class StoreUnidadAdministrativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', UnidadAdministrativa::class);
    }

    public function rules(): array
    {
        return [
            'codigo'          => ['required', 'string', 'max:50', 'unique:unidades_administrativas,codigo'],
            'nombre'          => ['required', 'string', 'max:255'],
            'descripcion'     => ['nullable', 'string'],
            'unidad_padre_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'nivel'           => ['required', 'integer', 'min:1'],
            'estado'          => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'          => 'El código de la unidad es obligatorio.',
            'codigo.unique'            => 'El código ingresado ya se encuentra en uso.',
            'nombre.required'          => 'El nombre de la unidad es obligatorio.',
            'unidad_padre_id.exists'   => 'La unidad padre seleccionada no es válida.',
            'nivel.required'           => 'El nivel jerárquico es obligatorio.',
            'nivel.min'                => 'El nivel mínimo debe ser 1.',
        ];
    }
}
