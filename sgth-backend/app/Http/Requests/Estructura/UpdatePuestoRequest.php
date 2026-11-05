<?php

namespace App\Http\Requests\Estructura;

use App\Enums\NivelComplejidadPuesto;
use App\Enums\RolPuesto;
use App\Models\Estructura\Puesto;
use App\Rules\GrupoOcupacionalDelRegimen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

final class UpdatePuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Devolvía `true` sin más: crear un puesto pedía `gestionar-puestos`,
        // pero editarlo no pedía nada.
        return $this->user()->can('update', $this->route('puesto'));
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
     * Lo que el puesto cumple entre sus campos (`CoherenciaDelPuesto`). En una
     * edición parcial, lo que no viene es lo que el puesto ya tiene: marcar
     * como jefatura un puesto de dos plazas tiene que fallar aunque no se
     * envíen las plazas.
     */
    public function after(): array
    {
        $guardado = $this->puestoGuardado();

        return [
            fn (Validator $validator) => CoherenciaDelPuesto::validar($validator, [
                'es_jefe'         => $this->has('es_jefe') ? $this->input('es_jefe') : $guardado?->es_jefe,
                'plazas'          => $this->has('plazas') ? $this->input('plazas') : $guardado?->plazas,
                'regimen_laboral' => $this->regimenDelPuesto(),
                'rol_puesto'      => $this->has('rol_puesto') ? $this->input('rol_puesto') : $guardado?->rol_puesto,
            ]),
        ];
    }

    private function puestoGuardado(): ?Puesto
    {
        $puesto = $this->route('puesto');

        return $puesto instanceof Puesto ? $puesto : Puesto::find($puesto);
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

        return $this->puestoGuardado()?->regimen_laboral;
    }
}
