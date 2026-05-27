<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email', 'unique:users,email'],
            'roles'       => ['required', 'array'],
            'roles.*'     => ['string', 'exists:roles,name'],
            'servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'cedula'      => ['nullable', 'string', 'regex:/^\d{10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'     => 'El correo electrónico es obligatorio.',
            'email.email'        => 'Debe proporcionar un correo válido.',
            'email.unique'       => 'Este correo ya está registrado.',
            'roles.required'     => 'Debe asignar al menos un rol.',
            'roles.*.exists'     => 'El rol seleccionado no existe.',
            'servidor_id.exists' => 'El servidor seleccionado no existe.',
        ];
    }
}
