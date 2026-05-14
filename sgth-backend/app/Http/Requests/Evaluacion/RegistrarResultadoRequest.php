<?php

namespace App\Http\Requests\Evaluacion;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarResultadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Se asume control por Policy o Middleware (jefe inmediato o UATH)
    }

    public function rules(): array
    {
        return [
            'calificacion_cuantitativa' => ['required', 'numeric', 'min:0', 'max:100'],
            'observaciones'             => ['nullable', 'string', 'max:1000'],
            'brechas_identificadas'     => ['nullable', 'string', 'max:2000'],
            'acciones_mejora'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'calificacion_cuantitativa.required' => 'La calificación es obligatoria.',
            'calificacion_cuantitativa.numeric'  => 'La calificación debe ser un número.',
            'calificacion_cuantitativa.min'      => 'La calificación mínima permitida es 0.',
            'calificacion_cuantitativa.max'      => 'La calificación máxima permitida es 100.',
        ];
    }
}
