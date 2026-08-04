<?php

namespace App\Http\Requests\Disciplinario;

use App\Enums\EstadoSumario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvanzarSumarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'resuelto' se excluye a propósito: la resolución va por
            // POST sumarios/{id}/resolver, que además aplica la sanción.
            'estado' => ['required', Rule::in([
                EstadoSumario::EN_INSTRUCCION->value,
                EstadoSumario::EN_PRUEBA->value,
                EstadoSumario::CON_INFORME->value,
                EstadoSumario::APELADO->value,
                EstadoSumario::CERRADO->value,
            ])],
            'fecha_notificacion'   => ['nullable', 'date'],
            'fecha_termino_prueba' => ['nullable', 'date'],
            'fecha_informe'        => ['nullable', 'date'],
        ];
    }
}
