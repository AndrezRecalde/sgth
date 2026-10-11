<?php

namespace App\Http\Requests\Disciplinario;

use Illuminate\Foundation\Http\FormRequest;

class StoreSumarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servidor_id'    => ['required', 'integer', 'exists:servidores,id'],
            'motivo'         => ['required', 'string', 'max:2000'],
            // Un sumario no se abre en el futuro, y si se abriera, el límite
            // de notificación quedaría también en el futuro y
            // `controlarPlazosLegales()` no lo vería caducar nunca.
            'fecha_apertura' => ['nullable', 'date', 'before_or_equal:today'],
            // La falta del reglamento interno que se imputa a un obrero (fase
            // 4.4). Que sea obligatoria ahí lo decide DisciplinarioService.
            'falta_reglamento_id' => ['nullable', 'integer', 'exists:faltas_reglamento_interno,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'servidor_id.required' => 'Seleccione el servidor sumariado.',
            'motivo.required'      => 'El motivo del sumario es obligatorio.',
            'fecha_apertura.before_or_equal' => 'El sumario no puede abrirse con una fecha futura.',
        ];
    }
}
