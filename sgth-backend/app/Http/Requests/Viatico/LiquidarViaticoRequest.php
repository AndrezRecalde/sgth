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
            'fecha_retorno'    => ['nullable', 'date'],
            'observaciones'    => ['nullable', 'string', 'max:1000'],

            // Actividades
            'actividades'               => ['nullable', 'array'],
            'actividades.*.fecha'       => ['required_with:actividades', 'date'],
            'actividades.*.hora_inicio' => ['nullable', 'string'],
            'actividades.*.hora_fin'    => ['nullable', 'string'],
            'actividades.*.descripcion' => ['required_with:actividades', 'string', 'min:5'],
            'actividades.*.lugar'       => ['required_with:actividades', 'string'],

            // Facturas nuevo modelo
            'facturas'                           => ['nullable', 'array'],
            'facturas.*.categoria_factura_id'    => ['required_with:facturas', 'integer', 'exists:categorias_factura,id'],
            'facturas.*.nombre_proveedor'        => ['required_with:facturas', 'string', 'max:200'],
            'facturas.*.monto'                   => ['required_with:facturas', 'numeric', 'min:0.01'],
            'facturas.*.tipo_comprobante'        => ['required_with:facturas', 'in:factura,ticket,recibo,otro'],
            'facturas.*.numero_factura'          => ['nullable', 'string', 'max:50'],
            'facturas.*.numero_ticket'           => ['nullable', 'string', 'max:50'],
            'facturas.*.ruc_proveedor'           => ['nullable', 'string', 'max:20'],
            'facturas.*.fecha_factura'           => ['nullable', 'date'],
            'facturas.*.detalle'                 => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'actividades.*.fecha.required_with'       => 'La fecha de la actividad es obligatoria.',
            'actividades.*.descripcion.required_with' => 'La descripción de la actividad es obligatoria.',
            'actividades.*.lugar.required_with'       => 'El lugar de la actividad es obligatorio.',
            'facturas.*.categoria_factura_id.required_with' => 'La categoría del comprobante es obligatoria.',
            'facturas.*.nombre_proveedor.required_with'     => 'El nombre del proveedor es obligatorio.',
            'facturas.*.monto.required_with'                => 'El monto del comprobante es obligatorio.',
            'facturas.*.tipo_comprobante.required_with'     => 'El tipo de comprobante es obligatorio.',
        ];
    }

    public function withValidator(
        \Illuminate\Validation\Validator $validator
    ): void {
        $validator->after(function ($v) {
            $facturas = $this->input('facturas', []);
            foreach ($facturas as $i => $factura) {
                $tipo = $factura['tipo_comprobante'] ?? '';
                $requiereRuc = in_array($tipo, [
                    'factura', 'recibo'
                ]);
                if ($requiereRuc &&
                    empty($factura['ruc_proveedor'])
                ) {
                    $v->errors()->add(
                        "facturas.{$i}.ruc_proveedor",
                        'El RUC es obligatorio para ' .
                        strtoupper($tipo) . '.'
                    );
                }
            }
        });
    }
}
