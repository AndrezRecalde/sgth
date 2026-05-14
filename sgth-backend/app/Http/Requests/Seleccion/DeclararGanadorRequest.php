<?php

namespace App\Http\Requests\Seleccion;

use Illuminate\Foundation\Http\FormRequest;

class DeclararGanadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado por middleware de rol en la ruta (ej: admin-uath)
    }

    public function rules(): array
    {
        return [
            'postulante_ganador_id' => ['required', 'integer', 'exists:postulantes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'postulante_ganador_id.required' => 'Debe especificar el postulante que ganó el concurso.',
            'postulante_ganador_id.exists'   => 'El postulante especificado no existe.',
        ];
    }
}
