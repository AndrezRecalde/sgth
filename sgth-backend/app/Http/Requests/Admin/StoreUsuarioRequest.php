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
            'usuario_ti'  => [
                'required',
                'string',
                'max:50',
                'unique:users,usuario_ti',
                'regex:/^[a-z0-9]+$/',
            ],
            'roles'       => ['required', 'array'],
            'roles.*'     => ['string', 'exists:roles,name'],
            'servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'cedula'      => ['nullable', 'string', 'regex:/^\d{10}$/'],
            'permisos'    => ['nullable', 'array'],
            'permisos.*'  => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'       => 'El correo electrónico es obligatorio.',
            'email.email'          => 'Debe proporcionar un correo válido.',
            'email.unique'         => 'Este correo ya está registrado.',
            'usuario_ti.required'  => 'El usuario TI es obligatorio.',
            'usuario_ti.unique'    => 'Este usuario TI ya está registrado.',
            'usuario_ti.regex'     => 'Solo letras minúsculas y números, sin espacios.',
            'roles.required'       => 'Debe asignar al menos un rol.',
            'roles.*.exists'       => 'El rol seleccionado no existe.',
            'servidor_id.exists'   => 'El servidor seleccionado no existe.',
        ];
    }
}
