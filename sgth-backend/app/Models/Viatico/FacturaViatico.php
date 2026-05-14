<?php

namespace App\Models\Viatico;

use App\Enums\ConceptoFactura;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaViatico extends Model
{
    use HasFactory;

    protected $table = 'facturas_viatico';

    protected $fillable = [
        'liquidacion_viatico_id',
        'concepto',
        'detalle',
        'numero_factura',
        'ruc_proveedor',
        'nombre_proveedor',
        'monto',
        'archivo_ruta',
        'archivo_nombre',
    ];

    protected function casts(): array
    {
        return [
            'concepto' => ConceptoFactura::class,
            'monto'    => 'decimal:2',
        ];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionViatico::class, 'liquidacion_viatico_id');
    }
}
