<?php

namespace App\Http\Requests\Viatico;

use Illuminate\Foundation\Http\FormRequest;

class StoreComisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10'],
            'unidad_administrativa_id' => ['required', 'exists:unidades_administrativas,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after:fecha_inicio'],
            'documento_autorizacion' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de la comisión es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos 10 caracteres.',
            'unidad_administrativa_id.required' => 'La unidad administrativa es obligatoria.',
            'unidad_administrativa_id.exists' => 'La unidad administrativa seleccionada no es válida.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }
}
