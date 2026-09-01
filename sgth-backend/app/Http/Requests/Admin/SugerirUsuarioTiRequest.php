<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class SugerirUsuarioTiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'servidor_id' => ['nullable', 'integer', 'exists:servidores,id'],
            'nombre'      => ['nullable', 'string', 'max:100'],
            'apellido'    => ['nullable', 'string', 'max:100'],
        ];
    }
}
