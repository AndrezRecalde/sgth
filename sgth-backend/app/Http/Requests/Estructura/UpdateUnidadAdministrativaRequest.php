<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUnidadAdministrativaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // En un API resource route, el parámetro se llama igual que el singular del recurso
        return $this->user()->can('update', $this->route('unidades_administrativa') ?? $this->route('unidad_administrativa'));
    }

    public function rules(): array
    {
        $unidad = $this->route('unidades_administrativa') ?? $this->route('unidad_administrativa');
        $unidadId = $unidad ? $unidad->id : null;

        return [
            'codigo'          => ['sometimes', 'string', 'max:50', 'unique:unidades_administrativas,codigo,' . $unidadId],
            'nombre'          => ['sometimes', 'string', 'max:255'],
            'acronimo'        => ['nullable', 'string', 'max:50'],
            'tipo_unidad_id'  => ['nullable', 'uuid', 'exists:tipos_unidad,id'],
            'descripcion'     => ['nullable', 'string'],
            'unidad_padre_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'nivel'           => ['sometimes', 'integer', 'min:1'],
            'estado'          => ['boolean'],
            // Anclan de qué unidad sale cada firmante de las Acciones de
            // Personal. Marcar otra unidad desmarca la anterior — lo hace
            // UnidadAdministrativaController para no chocar con el índice único.
            'es_unidad_talento_humano' => ['boolean'],
            'es_maxima_autoridad'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique'          => 'El código ingresado ya se encuentra en uso por otra unidad.',
            'acronimo.max'           => 'El acrónimo no puede superar los 50 caracteres.',
            'tipo_unidad_id.exists'  => 'El tipo de unidad seleccionado no es válido.',
            'tipo_unidad_id.uuid'    => 'El tipo de unidad debe ser un UUID válido.',
            'unidad_padre_id.exists' => 'La unidad padre seleccionada no es válida.',
            'nivel.min'              => 'El nivel mínimo debe ser 1.',
        ];
    }
}
