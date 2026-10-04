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
            // La notificación y el informe ya ocurrieron; el término del
            // período de prueba sí puede ser futuro. El orden entre hitos lo
            // comprueba DisciplinarioService::validarCronologia().
            'fecha_notificacion'   => ['nullable', 'date', 'before_or_equal:today'],
            'fecha_termino_prueba' => ['nullable', 'date'],
            'fecha_informe'        => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_notificacion.before_or_equal' => 'La notificación no puede tener fecha futura.',
            'fecha_informe.before_or_equal'      => 'El informe del instructor no puede tener fecha futura.',
        ];
    }
}
