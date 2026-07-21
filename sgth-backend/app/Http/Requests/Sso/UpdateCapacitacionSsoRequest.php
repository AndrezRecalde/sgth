<?php

namespace App\Http\Requests\Sso;

use App\Enums\Permiso;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCapacitacionSsoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permiso::GESTIONAR_SSO->value);
    }

    public function rules(): array
    {
        return [
            'tema'           => ['sometimes', 'required', 'string', 'max:200'],
            'fecha'          => ['sometimes', 'required', 'date'],
            'duracion_horas' => ['sometimes', 'required', 'numeric', 'min:0.5'],
            'instructor'     => ['sometimes', 'required', 'string', 'max:150'],
            'lugar'          => ['nullable', 'string', 'max:200'],
            'estado'         => ['boolean'],
        ];
    }
}
