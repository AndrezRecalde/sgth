<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class AsignarServidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(
            'vincularServidor',
            User::findOrFail($this->route('id')),
        );
    }

    public function rules(): array
    {
        return [
            'servidor_id' => ['required', 'integer', 'exists:servidores,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'servidor_id.required' => 'Debe seleccionar un servidor.',
            'servidor_id.exists'   => 'El servidor seleccionado no existe.',
        ];
    }
}
