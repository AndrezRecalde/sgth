<?php

namespace App\Http\Requests\InventarioTi;

use App\Enums\DocumentoIngresoConsumible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registrar lo que TIC recibe de bodega (fase 5.4a): un documento (egreso de
 * bodega, acta o memorando) con uno o varios consumibles [P8]. La fecha de
 * compra queda en el ingreso; el saldo es único, sin lotes [P2].
 */
final class RegistrarIngresoConsumibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El acceso lo resuelve el permiso de la ruta.
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fecha'                 => ['required', 'date', 'before_or_equal:today'],
            'tipo_documento'        => ['required', Rule::enum(DocumentoIngresoConsumible::class)],
            'numero_documento'      => ['required', 'string', 'min:1', 'max:80'],
            'fecha_compra'          => ['nullable', 'date', 'before_or_equal:fecha'],
            'observacion'           => ['nullable', 'string', 'max:500'],
            'items'                 => ['required', 'array', 'min:1', 'max:30'],
            'items.*.consumible_id' => ['required', 'integer', Rule::exists('consumibles', 'id')],
            'items.*.cantidad'      => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tipo_documento'        => 'documento',
            'numero_documento'      => 'número del documento',
            'fecha_compra'          => 'fecha de compra',
            'observacion'           => 'observación',
            'items'                 => 'consumibles',
            'items.*.consumible_id' => 'consumible',
            'items.*.cantidad'      => 'cantidad',
        ];
    }
}
