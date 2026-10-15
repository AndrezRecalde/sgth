<?php

namespace App\Http\Requests\Comunicacion;

use App\Enums\MotivoRevocacionCredencial;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Revocar una credencial: el motivo es obligatorio (docs/diseno-comunicacion-social.md, 6.3). */
class RevocarCredencialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'motivo'  => ['required', Rule::enum(MotivoRevocacionCredencial::class)],
            'detalle' => [
                Rule::requiredIf($this->input('motivo') === MotivoRevocacionCredencial::OTRO->value),
                'nullable', 'string', 'min:5', 'max:500',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'motivo'  => 'motivo',
            'detalle' => 'detalle',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'detalle.required' => 'Diga por qué se revoca la credencial.',
        ];
    }
}
