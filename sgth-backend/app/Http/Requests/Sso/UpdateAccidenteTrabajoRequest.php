<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccidenteTrabajoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('accidentetrabajo'));
    }

    public function rules(): array
    {
        return [
            // Reglas validadas
        ];
    }
}
