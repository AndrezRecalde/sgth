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
            'cargo_id'                  => ['required', 'integer', 'exists:cargos,id'],
            'unidad_administrativa_id'  => ['required', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional_id'      => ['nullable', 'integer', 'exists:grupos_ocupacionales,id'],
            'partida_presupuestaria_id' => ['nullable', 'integer', 'exists:partidas_presupuestarias,id'],
            'plazas'                    => ['required', 'integer', 'min:1'],
            'rol_puesto'                => ['nullable', new Enum(RolPuesto::class)],
            'nivel_complejidad'         => ['nullable', new Enum(NivelComplejidadPuesto::class)],
            'regimen_laboral'           => ['required', 'string', 'in:losep,codigo_trabajo'],
            'es_jefe'                   => ['boolean'],
            'activo'                    => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'cargo_id.required'                  => 'Seleccione el cargo.',
            'cargo_id.exists'                    => 'El cargo seleccionado no existe.',
            'unidad_administrativa_id.required'  => 'La unidad administrativa es obligatoria.',
            'unidad_administrativa_id.exists'    => 'La unidad administrativa no existe.',
            'plazas.required'                    => 'El número de plazas es obligatorio.',
            'plazas.min'                         => 'Debe haber al menos 1 plaza.',
            'regimen_laboral.required'           => 'El régimen laboral es obligatorio.',
        ];
    }
}
