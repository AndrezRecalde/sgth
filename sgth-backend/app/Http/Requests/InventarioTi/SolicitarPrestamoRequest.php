<?php

namespace App\Http\Requests\InventarioTi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * La solicitud de préstamo desde el Portal (fase 2.1): los equipos concretos
 * (D13), el horario, para qué y dónde. El horario contra las reglas de TIC, y
 * si los equipos se pueden prestar y están libres, lo comprueba el servicio.
 */
final class SolicitarPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        foreach (['motivo', 'lugar_uso'] as $campo) {
            if (is_string($this->input($campo))) {
                $this->merge([$campo => trim($this->input($campo))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'bienes'             => ['required', 'array', 'min:1', 'max:10'],
            'bienes.*'           => ['integer', 'distinct', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
            'inicio_previsto'    => ['required', 'date'],
            'fin_previsto'       => ['required', 'date', 'after:inicio_previsto'],
            'motivo'             => ['required', 'string', 'min:5', 'max:1000'],
            'lugar_uso'          => ['required', 'string', 'min:3', 'max:200'],
            'fuera_del_edificio' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'bienes'             => 'equipos',
            'bienes.*'           => 'equipo',
            'inicio_previsto'    => 'hora de entrega',
            'fin_previsto'       => 'hora de devolución',
            'motivo'             => 'motivo',
            'lugar_uso'          => 'lugar de uso',
            'fuera_del_edificio' => 'fuera del edificio',
        ];
    }
}
