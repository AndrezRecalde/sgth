<?php

namespace App\Http\Requests\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use App\Models\Estructura\Puesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class StorePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Puesto::class);
    }

    public function rules(): array
    {
        return [
            'codigo'                    => ['required', 'string', 'max:50', 'unique:puestos,codigo'],
            'denominacion'              => ['required', 'string', 'max:255'],
            'mision'                    => ['nullable', 'string'],
            'unidad_administrativa_id'  => ['required', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional_id'      => ['nullable', 'integer', 'exists:grupos_ocupacionales,id'],
            'partida_presupuestaria_id' => ['nullable', 'integer', 'exists:partidas_presupuestarias,id'],
            'plazas'                    => ['required', 'integer', 'min:1'],
            'rol_puesto'                => ['nullable', new Enum(RolPuesto::class)],
            'nivel_complejidad'         => ['nullable', new Enum(NivelComplejidadPuesto::class)],
            'nivel_jerarquico'          => ['nullable', 'integer', 'min:1'],
            'regimen_laboral'           => ['required', 'string', 'in:losep,codigo_trabajo'],
            'es_jefe'                   => ['boolean'],
            'activo'                    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'                   => 'El código del puesto es obligatorio.',
            'codigo.unique'                     => 'Este código ya está en uso.',
            'denominacion.required'             => 'La denominación es obligatoria.',
            'unidad_administrativa_id.required' => 'La unidad administrativa es obligatoria.',
            'unidad_administrativa_id.exists'   => 'La unidad administrativa no existe.',
            'grupo_ocupacional_id.exists'       => 'El grupo ocupacional no existe.',
            'plazas.required'                   => 'El número de plazas es obligatorio.',
            'plazas.min'                        => 'Debe haber al menos 1 plaza.',
            'regimen_laboral.required'          => 'El régimen laboral es obligatorio.',
        ];
    }
}
