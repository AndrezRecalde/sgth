<?php

namespace App\Http\Requests\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use App\Models\Estructura\Puesto;
use App\Rules\GrupoOcupacionalDelRegimen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdatePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cargo_id'                  => ['sometimes', 'integer', 'exists:cargos,id'],
            'unidad_administrativa_id'  => ['sometimes', 'integer', 'exists:unidades_administrativas,id'],
            'grupo_ocupacional_id'      => [
                'nullable', 'integer', 'exists:grupos_ocupacionales,id',
                new GrupoOcupacionalDelRegimen($this->regimenDelPuesto()),
            ],
            'partida_presupuestaria_id' => ['nullable', 'integer', 'exists:partidas_presupuestarias,id'],
            'plazas'                    => ['sometimes', 'integer', 'min:1'],
            'rol_puesto'                => ['nullable', new Enum(RolPuesto::class)],
            'nivel_complejidad'         => ['nullable', new Enum(NivelComplejidadPuesto::class)],
            'regimen_laboral'           => ['sometimes', 'string', 'in:losep,codigo_trabajo'],
            'es_jefe'                   => ['boolean'],
            'activo'                    => ['boolean'],
        ];
    }

    /**
     * El régimen contra el que se valida el grupo ocupacional.
     *
     * En una edición parcial el régimen puede no venir en la petición —se está
     * cambiando solo el grado, por ejemplo—, así que se cae al que ya tiene
     * guardado el puesto. Comparar contra `null` dejaría pasar cualquier grado.
     */
    private function regimenDelPuesto(): ?string
    {
        if ($this->filled('regimen_laboral')) {
            return $this->input('regimen_laboral');
        }

        $puesto = $this->route('puesto');

        return $puesto instanceof Puesto
            ? $puesto->regimen_laboral
            : Puesto::find($puesto)?->regimen_laboral;
    }
}
