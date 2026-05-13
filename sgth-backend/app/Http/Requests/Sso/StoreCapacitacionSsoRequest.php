<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class StoreCapacitacionSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Sso\CapacitacionSso::class);
    }

    public function rules(): array
    {
        return [
            // Reglas validadas
        ];
    }
}
