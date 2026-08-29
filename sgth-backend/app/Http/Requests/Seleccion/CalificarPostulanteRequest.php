<?php

namespace App\Http\Requests\Seleccion;

use Illuminate\Foundation\Http\FormRequest;

class CalificarPostulanteRequest extends FormRequest
{
    /**
     * Antes esto era `can('evaluate', $this->route('postulante'))`, y denegaba
     * siempre por dos motivos a la vez: el parámetro de la ruta se llama `id`,
     * no `postulante` —así que llegaba `null`—, y nunca se escribió una policy
     * de Postulante ni un gate `evaluate`. El endpoint devolvía 403 a todo el
     * mundo, incluido un administrador.
     *
     * Se sustituye por la misma condición que ya aplica el grupo de rutas, de
     * forma explícita. No se inventa una regla de negocio nueva: si más adelante
     * hace falta acotar quién evalúa —por unidad, por convocatoria asignada—,
     * eso es una policy de verdad y se escribe aquí.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyRole([
            'admin-uath', 'analista-uath', 'admin-ti',
        ]);
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
