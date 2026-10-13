<?php

namespace App\Http\Requests\InventarioTi;

use App\Enums\CondicionFisicaBien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Recibir un préstamo en curso (fase 2.1): la fecha y hora reales y, por cada
 * equipo, la condición con que vuelve y la novedad, si la hay.
 */
final class RecibirPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'devuelto_en'                  => ['required', 'date'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.id'                   => ['required', 'integer', 'distinct'],
            'items.*.condicion_devolucion' => ['required', Rule::enum(CondicionFisicaBien::class)],
            'items.*.novedad'              => ['nullable', 'string', 'max:1000'],
            'observaciones'                => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'devuelto_en'                  => 'fecha y hora de devolución',
            'items'                        => 'equipos',
            'items.*.condicion_devolucion' => 'condición a la devolución',
            'items.*.novedad'              => 'novedad',
        ];
    }
}
