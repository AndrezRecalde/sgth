<?php

namespace App\Http\Requests\InventarioTi;

use App\Enums\CondicionFisicaBien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entregar un préstamo reservado (fase 2.1): la fecha y hora reales y, por
 * cada equipo, los accesorios y la condición con que sale.
 */
final class EntregarPrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'entregado_en'              => ['required', 'date'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.id'                => ['required', 'integer', 'distinct'],
            'items.*.accesorios'        => ['nullable', 'string', 'max:300'],
            'items.*.condicion_entrega' => ['required', Rule::enum(CondicionFisicaBien::class)],
            'observaciones'             => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'entregado_en'              => 'fecha y hora de entrega',
            'items'                     => 'equipos',
            'items.*.accesorios'        => 'accesorios',
            'items.*.condicion_entrega' => 'condición a la entrega',
        ];
    }
}
