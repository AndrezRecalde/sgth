<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('usuario'));
    }

    public function rules(): array
    {
        // Se asume que el parámetro de ruta se llama 'usuario'
        $usuarioId = $this->route('usuario') ? $this->route('usuario')->id : null;

        return [
            'nombre'   => ['sometimes', 'string', 'max:100'],
            'apellido' => ['sometimes', 'string', 'max:100'],
            'email'    => ['sometimes', 'email', 'unique:users,email,' . $usuarioId],
            'roles'    => ['sometimes', 'array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'    => 'Debe proporcionar un correo electrónico válido.',
            'email.unique'   => 'Este correo electrónico ya está registrado por otro usuario.',
            'roles.*.exists' => 'El rol seleccionado no existe en el sistema.',
        ];
    }
}
