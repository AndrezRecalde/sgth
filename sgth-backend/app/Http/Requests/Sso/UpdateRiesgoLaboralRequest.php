<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiesgoLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('riesgolaboral'));
    }

    public function rules(): array
    {
        return [
            // Reglas validadas
        ];
    }
}
