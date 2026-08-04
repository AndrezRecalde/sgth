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
            'fecha_apertura' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'servidor_id.required' => 'Seleccione el servidor sumariado.',
            'motivo.required'      => 'El motivo del sumario es obligatorio.',
        ];
    }
}
