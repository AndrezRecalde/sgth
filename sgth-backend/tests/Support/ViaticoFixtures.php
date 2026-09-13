<?php

use App\Models\Viatico\CategoriaFactura;
use App\Models\Viatico\FacturaViatico;
use App\Models\Viatico\LiquidacionViatico;

/**
 * Un comprobante de hospedaje ya aceptado por Financiero: lo mínimo para que
 * una liquidación se pueda contabilizar.
 */
function comprobanteAceptado(LiquidacionViatico $liquidacion, array $atributos = []): FacturaViatico
{
    $categoria = CategoriaFactura::firstOrCreate(
        ['codigo' => 'HOSP'],
        ['nombre' => 'Hospedaje', 'grupo' => 'viatico', 'activo' => true],
    );

    return FacturaViatico::create(array_merge([
        'liquidacion_viatico_id' => $liquidacion->id,
        'categoria_factura_id'   => $categoria->id,
        'tipo_comprobante'       => 'factura',
        'numero_factura'         => '001-001-'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'ruc_proveedor'          => '1790016919001',
        'nombre_proveedor'       => 'Hotel de prueba',
        'monto'                  => 50,
        'estado_revision'        => 'aceptada',
    ], $atributos));
}