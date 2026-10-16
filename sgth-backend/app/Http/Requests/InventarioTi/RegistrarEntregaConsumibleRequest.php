<?php

namespace App\Http\Requests\InventarioTi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entregar consumibles (fase 5.4a): uno o varios a la vez, a un equipo (los de
 * impresión, siempre) y a quien retira, que por defecto es el custodio del
 * equipo. Opcionalmente, el ticket del pedido [P3].
 */
final class RegistrarEntregaConsumibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha'                 => ['required', 'date', 'before_or_equal:today'],
            'bien_informatico_id'   => ['nullable', 'integer', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
            'servidor_retira_id'    => ['nullable', 'integer', Rule::exists('servidores', 'id')],
            'ticket_id'             => ['nullable', 'integer', Rule::exists('tickets', 'id')->whereNull('deleted_at')],
            'observacion'           => ['nullable', 'string', 'max:500'],
            'items'                 => ['required', 'array', 'min:1', 'max:30'],
            'items.*.consumible_id' => ['required', 'integer', Rule::exists('consumibles', 'id')],
            'items.*.cantidad'      => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'bien_informatico_id'   => 'equipo',
            'servidor_retira_id'    => 'quien retira',
            'ticket_id'             => 'ticket',
            'observacion'           => 'observación',
            'items'                 => 'consumibles',
            'items.*.consumible_id' => 'consumible',
            'items.*.cantidad'      => 'cantidad',
        ];
    }
}
