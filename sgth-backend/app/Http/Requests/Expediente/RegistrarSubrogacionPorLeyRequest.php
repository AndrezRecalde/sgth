<?php

namespace App\Http\Requests\Expediente;

use App\Enums\MotivoSubrogacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La subrogación por ley del Viceprefecto que Talento Humano registra a mano
 * (fase 4.1b): las vacaciones u otra ausencia del Prefecto que no nació de una
 * licencia del Consejo. Que pase de tres días lo valida AutoridadElectaService.
 */
class RegistrarSubrogacionPorLeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio'      => ['required', 'date'],
            'fecha_fin'         => ['required', 'date', 'after:fecha_inicio'],
            'motivo'            => ['required', Rule::in(array_map(fn (MotivoSubrogacion $m) => $m->value, [
                MotivoSubrogacion::VACACIONES, MotivoSubrogacion::LICENCIA, MotivoSubrogacion::ENFERMEDAD,
                MotivoSubrogacion::COMISION, MotivoSubrogacion::OTRO,
            ]))],
            // El oficio que comunica la ausencia o la resolución del Consejo [TH 6].
            'resolucion_numero' => ['required', 'string', 'max:100'],
            'observacion'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'resolucion_numero' => 'oficio o resolución',
        ];
    }
}
