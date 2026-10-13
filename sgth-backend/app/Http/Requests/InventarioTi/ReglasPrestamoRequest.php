<?php

namespace App\Http\Requests\InventarioTi;

use Illuminate\Foundation\Http\FormRequest;

/** Las reglas del préstamo que configura TIC (D16). */
final class ReglasPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plazo_maximo_dias_habiles'   => ['required', 'integer', 'min:1', 'max:30'],
            'anticipacion_minima_minutos' => ['required', 'integer', 'min:0', 'max:10080'],
            'hora_apertura'               => ['required', 'date_format:H:i'],
            'hora_cierre'                 => ['required', 'date_format:H:i'],
            'presta_fines_de_semana'      => ['required', 'boolean'],
            'bloquea_con_vencido'         => ['required', 'boolean'],
            'margen_retiro_minutos'       => ['required', 'integer', 'min:0', 'max:1440'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'plazo_maximo_dias_habiles'   => 'plazo máximo',
            'anticipacion_minima_minutos' => 'anticipación mínima',
            'hora_apertura'               => 'hora de apertura',
            'hora_cierre'                 => 'hora de cierre',
            'presta_fines_de_semana'      => 'préstamo en fin de semana',
            'bloquea_con_vencido'         => 'bloqueo con préstamo vencido',
            'margen_retiro_minutos'       => 'margen para retirar',
        ];
    }
}
