<?php

namespace App\Http\Requests\Estructura;

use Illuminate\Foundation\Http\FormRequest;

/** El puesto de contrato al que pasa un ocasional o un profesional (P9, PR 4). */
class ReubicarContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'puesto_destino_id' => ['required', 'integer', 'exists:puestos,id'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['puesto_destino_id' => 'puesto de contrato'];
    }
}
