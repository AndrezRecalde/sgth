<?php

namespace App\Http\Requests\Estructura;

use App\Models\Estructura\Puesto;
use Illuminate\Foundation\Http\FormRequest;

final class StorePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Puesto::class);
    }

    public function rules(): array
    {
        return [
            'codigo'                   => ['required', 'string', 'max:50', 'unique:puestos,codigo'],
            'denominacion'             => ['required', 'string', 'max:255'],
            'unidad_administrativa_id' => ['required', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional'        => ['required', 'string', 'max:100'],
            'grado_rmu'                => ['required', 'integer', 'min:1'],
            'rmu'                      => ['required', 'numeric', 'min:0'],
            'es_jefe'                  => ['boolean'],
            'nivel'                    => ['required', 'integer', 'min:1'],
            'estado'                   => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'                   => 'El código del puesto es obligatorio.',
            'codigo.unique'                     => 'El código del puesto ya existe.',
            'denominacion.required'             => 'La denominación del puesto es obligatoria.',
            'unidad_administrativa_id.required' => 'La unidad administrativa es obligatoria.',
            'unidad_administrativa_id.exists'   => 'La unidad administrativa seleccionada no es válida.',
            'grupo_ocupacional.required'        => 'El grupo ocupacional es obligatorio.',
            'grado_rmu.required'                => 'El grado RMU es obligatorio.',
            'rmu.required'                      => 'La remuneración mensual unificada (RMU) es obligatoria.',
            'nivel.required'                    => 'El nivel jerárquico es obligatorio.',
        ];
    }
}
