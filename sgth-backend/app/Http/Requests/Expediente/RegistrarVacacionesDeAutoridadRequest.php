<?php

namespace App\Http\Requests\Expediente;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Las vacaciones que el Consejo Provincial concede a una autoridad electa
 * (COOTAD Art. 47 s; TH 2026-11-26; fase 4.1d).
 *
 * No se pregunta el motivo, ni el jefe que autoriza, ni con o sin
 * remuneración: son vacaciones anuales, las concede el Consejo y las vacaciones
 * son siempre con sueldo. Los dos topes —los 60 días del Consejo y los días que
 * tiene acumulados— los valida `AutoridadElectaService`.
 */
class RegistrarVacacionesDeAutoridadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servidor_id'       => ['required', 'integer', 'exists:servidores,id'],
            'fecha_inicio'      => ['required', 'date'],
            // `after_or_equal` y no `after`: el Consejo puede conceder un día.
            'fecha_fin'         => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'resolucion_numero' => ['required', 'string', 'max:100'],
            'observacion'       => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'servidor_id'       => 'autoridad',
            'resolucion_numero' => 'resolución del Consejo',
        ];
    }
}
