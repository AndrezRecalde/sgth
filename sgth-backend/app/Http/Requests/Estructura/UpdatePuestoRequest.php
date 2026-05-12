<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('puesto'));
    }

    public function rules(): array
    {
        $puestoId = $this->route('puesto') ? $this->route('puesto')->id : null;

        return [
            'codigo'                   => ['sometimes', 'string', 'max:50', 'unique:puestos,codigo,' . $puestoId],
            'denominacion'             => ['sometimes', 'string', 'max:255'],
            'unidad_administrativa_id' => ['sometimes', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional'        => ['sometimes', 'string', 'max:100'],
            'grado_rmu'                => ['sometimes', 'integer', 'min:1'],
            'rmu'                      => ['sometimes', 'numeric', 'min:0'],
            'es_jefe'                  => ['boolean'],
            'nivel'                    => ['sometimes', 'integer', 'min:1'],
            'estado'                   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique'                     => 'El código del puesto ya está en uso.',
            'unidad_administrativa_id.exists'   => 'La unidad administrativa seleccionada no es válida.',
        ];
    }
}
