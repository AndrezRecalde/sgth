<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccidenteTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Sso\AccidenteTrabajo::class);
    }

    public function rules(): array
    {
        return [
            // Reglas validadas
        ];
    }
}
