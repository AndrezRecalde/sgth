<?php

namespace App\Http\Requests\Sso;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipoProteccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('equipoproteccion'));
    }

    public function rules(): array
    {
        return [
            // Reglas validadas
        ];
    }
}
