<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCapacitacionSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('capacitacionsso'));
    }

    public function rules(): array
    {
        return [
            // Reglas validadas
        ];
    }
}
