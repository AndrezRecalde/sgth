<?php

namespace App\Http\Requests\Viatico;

use Illuminate\Foundation\Http\FormRequest;

class LiquidarViaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'fecha_retorno'       => ['required', 'date'],
            'observaciones'       => ['nullable', 'string', 'max:1000'],
            'facturas'            => ['nullable', 'array'],
            'facturas.*.numero'   => ['required_with:facturas', 'string'],
            'facturas.*.proveedor'=> ['required_with:facturas', 'string'],
            'facturas.*.monto'    => ['required_with:facturas', 'numeric', 'min:0.01'],
        ];
    }
}
