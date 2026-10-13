<?php

namespace App\Http\Requests\InventarioTi;

use App\Enums\CondicionFisicaBien;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * El préstamo directo en el mostrador (fase 2.1): a quién, qué equipos con sus
 * accesorios y su condición, y hasta cuándo. Empieza ahora.
 */
final class PrestamoDirectoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'servidor_id'                 => ['required', 'integer', 'exists:servidores,id'],
            'items'                       => ['required', 'array', 'min:1', 'max:10'],
            'items.*.bien_informatico_id' => ['required', 'integer', 'distinct', Rule::exists('bienes_informaticos', 'id')->whereNull('deleted_at')],
            'items.*.accesorios'          => ['nullable', 'string', 'max:300'],
            'items.*.condicion_entrega'   => ['required', Rule::enum(CondicionFisicaBien::class)],
            'fin_previsto'                => ['required', 'date', 'after:now'],
            'motivo'                      => ['required', 'string', 'min:5', 'max:1000'],
            'lugar_uso'                   => ['required', 'string', 'min:3', 'max:200'],
            'fuera_del_edificio'          => ['sometimes', 'boolean'],
            'observaciones'               => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'servidor_id'                 => 'servidor',
            'items'                       => 'equipos',
            'items.*.bien_informatico_id' => 'equipo',
            'items.*.accesorios'          => 'accesorios',
            'items.*.condicion_entrega'   => 'condición a la entrega',
            'fin_previsto'                => 'hora de devolución',
            'lugar_uso'                   => 'lugar de uso',
        ];
    }
}
