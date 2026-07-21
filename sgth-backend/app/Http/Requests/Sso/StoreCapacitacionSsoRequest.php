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
            'tema'            => ['required', 'string', 'max:200'],
            'fecha'           => ['required', 'date'],
            'duracion_horas'  => ['required', 'numeric', 'min:0.5'],
            'instructor'      => ['required', 'string', 'max:150'],
            'lugar'           => ['nullable', 'string', 'max:200'],
            'estado'          => ['boolean'],
        ];
    }
}
