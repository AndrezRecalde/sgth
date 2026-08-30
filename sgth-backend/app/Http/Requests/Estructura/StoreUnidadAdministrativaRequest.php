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
            'tipo_unidad_id'  => ['required', 'uuid', 'exists:tipos_unidad,id'],
            'descripcion'     => ['nullable', 'string'],
            // Quién es el padre define el nivel; el nivel no se recibe. Es la
            // única forma de que el número y el árbol no puedan contradecirse.
            'unidad_padre_id' => ['nullable', 'integer', 'exists:unidades_administrativas,id'],
            'estado'          => ['boolean'],
            // Anclan de qué unidad sale cada firmante de las Acciones de
            // Personal. Marcar otra desmarca la anterior — lo hace
            // EstructuraService para no chocar con el índice único.
            'es_unidad_talento_humano' => ['boolean'],
            'es_maxima_autoridad'      => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'          => 'El código de la unidad es obligatorio.',
            'codigo.unique'            => 'El código ingresado ya se encuentra en uso.',
            'nombre.required'          => 'El nombre de la unidad es obligatorio.',
            'acronimo.max'             => 'El acrónimo no puede superar los 50 caracteres.',
            'tipo_unidad_id.required'  => 'El tipo de proceso es obligatorio: el organigrama agrupa las unidades por él.',
            'tipo_unidad_id.exists'    => 'El tipo de unidad seleccionado no es válido.',
            'tipo_unidad_id.uuid'      => 'El tipo de unidad debe ser un UUID válido.',
            'unidad_padre_id.exists'   => 'La unidad padre seleccionada no es válida.',
        ];
    }
}
