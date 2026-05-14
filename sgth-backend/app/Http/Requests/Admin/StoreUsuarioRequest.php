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
            'nombre'   => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'cedula'   => ['required', 'string', 'regex:/^\d{10}$/'],
            'roles'    => ['required', 'array'],
            'roles.*'  => ['string', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required'    => 'El correo electrónico es obligatorio.',
            'email.email'       => 'Debe proporcionar un correo electrónico válido.',
            'email.unique'      => 'Este correo electrónico ya está registrado.',
            'cedula.required'   => 'La cédula es obligatoria.',
            'cedula.regex'      => 'La cédula debe contener exactamente 10 dígitos numéricos.',
            'roles.required'    => 'Debe asignar al menos un rol al usuario.',
            'roles.*.exists'    => 'El rol seleccionado no existe en el sistema.',
        ];
    }
}
