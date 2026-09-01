<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->route('usuario');
        $usuarioModel = is_object($usuario) ? $usuario : User::findOrFail($usuario);

        return $this->user()->can('update', $usuarioModel);
    }

    public function rules(): array
    {
        // Se asume que el parámetro de ruta se llama 'usuario'
        $usuario = $this->route('usuario');
        $usuarioId = is_object($usuario) ? $usuario->id : $usuario;

        // 'nombre' y 'apellido' se validaban aquí hasta que la migración de
        // 2026-05-27 movió esos datos a la ficha de servidor y eliminó las
        // columnas de users. El servicio nunca los usó.
        return [
            'email'      => ['sometimes', 'email', 'unique:users,email,' . $usuarioId],
            'usuario_ti' => [
                'sometimes',
                'string',
                'max:50',
                'unique:users,usuario_ti,' . $usuarioId,
                'regex:/^[a-z0-9]+$/',
            ],
            'roles'      => ['sometimes', 'array'],
            'roles.*'    => ['string', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.email'       => 'Debe proporcionar un correo electrónico válido.',
            'email.unique'      => 'Este correo electrónico ya está registrado por otro usuario.',
            'usuario_ti.unique' => 'Este usuario TI ya está registrado por otro usuario.',
            'usuario_ti.regex'  => 'El usuario TI solo puede contener letras minúsculas y números.',
            'roles.*.exists'    => 'El rol seleccionado no existe en el sistema.',
        ];
    }
}
