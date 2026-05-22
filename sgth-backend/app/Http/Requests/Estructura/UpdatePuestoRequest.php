<?php

namespace App\Http\Requests\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class UpdatePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $puestoId = $this->route('puesto');

        return [
            'codigo'                    => ['sometimes', 'string', 'max:50',
                Rule::unique('puestos', 'codigo')->ignore($puestoId)],
            'denominacion'              => ['sometimes', 'string', 'max:255'],
            'mision'                    => ['nullable', 'string'],
            'unidad_administrativa_id'  => ['sometimes', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional_id'      => ['nullable', 'integer', 'exists:grupos_ocupacionales,id'],
            'partida_presupuestaria_id' => ['nullable', 'integer', 'exists:partidas_presupuestarias,id'],
            'plazas'                    => ['sometimes', 'integer', 'min:1'],
            'rol_puesto'                => ['nullable', new Enum(RolPuesto::class)],
            'nivel_complejidad'         => ['nullable', new Enum(NivelComplejidadPuesto::class)],
            'nivel_jerarquico'          => ['nullable', 'integer', 'min:1'],
            'regimen_laboral'           => ['sometimes', 'string', 'in:losep,codigo_trabajo'],
            'es_jefe'                   => ['boolean'],
            'activo'                    => ['boolean'],
        ];
    }
}
