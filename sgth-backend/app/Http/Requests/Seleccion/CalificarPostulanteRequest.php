<?php

namespace App\Http\Requests\Seleccion;

use Illuminate\Foundation\Http\FormRequest;

class CalificarPostulanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('evaluate', $this->route('postulante'));
    }

    public function rules(): array
    {
        return [
            'puntaje_meritos'   => ['required', 'numeric', 'min:0', 'max:40'],
            'puntaje_oposicion' => ['required', 'numeric', 'min:0', 'max:60'],
            'observaciones'     => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'puntaje_meritos.required'   => 'El puntaje de méritos es obligatorio.',
            'puntaje_meritos.max'        => 'Los méritos no pueden superar los 40 puntos (40%).',
            'puntaje_oposicion.required' => 'El puntaje de oposición es obligatorio.',
            'puntaje_oposicion.max'      => 'La oposición no puede superar los 60 puntos (60%).',
        ];
    }
}
