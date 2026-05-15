<?php

namespace App\Http\Requests\Viatico;

use App\Enums\ConceptoFactura;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreFacturaViaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'concepto' => ['required', new Enum(ConceptoFactura::class)],
            'numero_factura' => ['required', 'string', 'max:50'],
            'ruc_proveedor' => ['required', 'digits:13'],
            'nombre_proveedor' => ['required', 'string', 'max:255'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'archivo_ruta' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'concepto.required' => 'El concepto de la factura es obligatorio.',
            'numero_factura.required' => 'El número de factura es obligatorio.',
            'ruc_proveedor.required' => 'El RUC del proveedor es obligatorio.',
            'ruc_proveedor.digits' => 'El RUC debe tener exactamente 13 dígitos.',
            'nombre_proveedor.required' => 'El nombre del proveedor es obligatorio.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.min' => 'El monto debe ser mayor a 0.',
        ];
    }
}
