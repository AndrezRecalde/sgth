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
            'acronimo'        => ['nullable', 'string', 'max:50'],
            'tipo_unidad_id'  => ['nullable', 'uuid', 'exists:tipos_unidad,id'],
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
            'acronimo.max'             => 'El acrónimo no puede superar los 50 caracteres.',
            'tipo_unidad_id.exists'    => 'El tipo de unidad seleccionado no es válido.',
            'tipo_unidad_id.uuid'      => 'El tipo de unidad debe ser un UUID válido.',
            'unidad_padre_id.exists'   => 'La unidad padre seleccionada no es válida.',
            'nivel.required'           => 'El nivel jerárquico es obligatorio.',
            'nivel.min'                => 'El nivel mínimo debe ser 1.',
        ];
    }
}
