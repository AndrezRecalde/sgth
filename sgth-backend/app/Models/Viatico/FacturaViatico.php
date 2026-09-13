<?php

namespace App\Models\Viatico;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaViatico extends Model
{
    use HasFactory;

    protected $table = 'facturas_viatico';

    protected $fillable = [
        'liquidacion_viatico_id',
        'categoria_factura_id',
        'fecha_factura',
        'tipo_comprobante',
        'numero_ticket',
        'detalle',
        'numero_factura',
        'ruc_proveedor',
        'nombre_proveedor',
        'monto',
        'archivo_ruta',
        'archivo_nombre',
        'estado_revision',
        'observacion_revision',
        'revisado_por',
        'revisado_en',
    ];

    protected function casts(): array
    {
        return [
            'monto'       => 'decimal:2',
            'fecha_factura' => 'date:Y-m-d',
            'revisado_en' => 'datetime',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionViatico::class, 'liquidacion_viatico_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaFactura::class, 'categoria_factura_id');
    }
}
