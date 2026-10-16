<?php

namespace App\Http\Requests\InventarioTi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Generar la solicitud a bodega (fase 5.4c). Desde un ticket, el servidor y el
 * equipo salen de él; sin ticket, se dicen aquí. Hasta 25 renglones, como el
 * formulario de hoy.
 */
final class StoreSolicitudBodegaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('motivo'))) {
            $this->merge(['motivo' => trim($this->input('motivo'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ticket_id'             => ['nullable', 'integer', Rule::exists('tickets', 'id')->whereNull('deleted_at')],
            'servidor_id'           => ['required_without:ticket_id', 'nullable', 'integer', Rule::exists('servidores', 'id')],
            'bien_informatico_id'   => ['nullable', 'integer', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
            'fecha'                 => ['nullable', 'date', 'before_or_equal:today'],
            'motivo'                => ['required', 'string', 'min:5', 'max:500'],
            'items'                 => ['required', 'array', 'min:1', 'max:25'],
            'items.*.consumible_id' => ['required', 'integer', Rule::exists('consumibles', 'id')],
            'items.*.cantidad'      => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['servidor_id.required_without' => 'Diga de parte de quién es la solicitud.'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ticket_id'             => 'ticket',
            'servidor_id'           => 'servidor',
            'bien_informatico_id'   => 'equipo',
            'motivo'                => 'para qué es',
            'items'                 => 'consumibles',
            'items.*.consumible_id' => 'consumible',
            'items.*.cantidad'      => 'cantidad',
        ];
    }
}
