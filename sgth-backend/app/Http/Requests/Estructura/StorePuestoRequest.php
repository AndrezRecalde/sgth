<?php

namespace App\Http\Requests\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use App\Enums\TipoPuesto;
use App\Models\Estructura\Puesto;
use App\Rules\GrupoOcupacionalDelRegimen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

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
            'grupo_ocupacional_id'      => [
                'nullable', 'integer', 'exists:grupos_ocupacionales,id',
                new GrupoOcupacionalDelRegimen($this->input('regimen_laboral')),
            ],
            'partida_presupuestaria_id' => ['nullable', 'integer', 'exists:partidas_presupuestarias,id'],
            // Sin tipo, de plantilla: como nacían todos los puestos (P9).
            'tipo'                      => ['sometimes', Rule::enum(TipoPuesto::class)],
            // Obligatorias en uno de plantilla, prohibidas en uno de contrato:
            // lo decide `CoherenciaDelPuesto`.
            'plazas'                    => ['nullable', 'integer', 'min:1'],
            'rol_puesto'                => ['nullable', new Enum(RolPuesto::class)],
            'nivel_complejidad'         => ['nullable', new Enum(NivelComplejidadPuesto::class)],
            'regimen_laboral'           => ['required', 'string', 'in:losep,codigo_trabajo'],
            'es_jefe'                   => ['boolean'],
            'activo'                    => ['boolean'],
        ];
    }

    /** Lo que el puesto cumple entre sus campos: ver `CoherenciaDelPuesto`. */
    public function after(): array
    {
        return [
            fn (Validator $validator) => CoherenciaDelPuesto::validar($validator, [
                'tipo'            => $this->input('tipo', TipoPuesto::PLANTILLA->value),
                'es_jefe'         => $this->input('es_jefe', false),
                'plazas'          => $this->input('plazas'),
                'regimen_laboral' => $this->input('regimen_laboral'),
                'rol_puesto'      => $this->input('rol_puesto'),
            ]),
        ];
    }

    public function messages(): array
    {
        return [
            'cargo_id.required'                  => 'Seleccione el cargo.',
            'cargo_id.exists'                    => 'El cargo seleccionado no existe.',
            'unidad_administrativa_id.required'  => 'La unidad administrativa es obligatoria.',
            'unidad_administrativa_id.exists'    => 'La unidad administrativa no existe.',
            'plazas.min'                         => 'Debe haber al menos 1 plaza.',
            'regimen_laboral.required'           => 'El régimen laboral es obligatorio.',
        ];
    }
}
