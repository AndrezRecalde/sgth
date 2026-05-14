<?php

namespace App\Http\Requests\Asistencia;

use Illuminate\Foundation\Http\FormRequest;

class StoreVacacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date', 'after_or_equal:today'],
            'fecha_fin'    => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_inicio.required'       => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date'           => 'La fecha de inicio debe tener un formato válido.',
            'fecha_inicio.after_or_equal' => 'Las vacaciones no pueden solicitarse en fechas pasadas.',
            'fecha_fin.required'          => 'La fecha de finalización es obligatoria.',
            'fecha_fin.date'              => 'La fecha de finalización debe tener un formato válido.',
            'fecha_fin.after_or_equal'    => 'La fecha de finalización debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
