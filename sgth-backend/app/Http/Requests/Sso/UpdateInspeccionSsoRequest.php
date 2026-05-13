<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInspeccionSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('inspeccionsso'));
    }

    public function rules(): array
    {
        return [
            // Reglas validadas
        ];
    }
}
