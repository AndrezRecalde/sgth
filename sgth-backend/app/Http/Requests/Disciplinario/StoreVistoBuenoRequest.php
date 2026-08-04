<?php

namespace App\Http\Requests\Disciplinario;

use App\Enums\CausalVistoBueno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreVistoBuenoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'servidor_id'        => ['required', 'integer', 'exists:servidores,id'],
            'causal'             => ['required', new Enum(CausalVistoBueno::class)],
            'hechos'             => ['required', 'string', 'max:5000'],
            'fecha_solicitud'    => ['required', 'date'],
            'numero_tramite_mdt' => ['nullable', 'string', 'max:50'],
            'inspectoria'        => ['nullable', 'string', 'max:150'],
            'inspector_nombre'   => ['nullable', 'string', 'max:150'],
            'documento_respaldo' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'servidor_id.required'     => 'Seleccione el trabajador.',
            'causal.required'          => 'Indique la causal del Art. 172 del Código del Trabajo.',
            'hechos.required'          => 'Debe relatarse el fundamento de hecho de la solicitud.',
            'fecha_solicitud.required' => 'Indique la fecha de presentación de la solicitud.',
        ];
    }
}
