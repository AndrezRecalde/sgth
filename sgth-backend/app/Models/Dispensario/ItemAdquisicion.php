<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAdquisicion extends Model
{
    use HasFactory;

    protected $table = 'items_adquisicion';

    protected $fillable = [
        'adquisicion_id', 'inventario_medicina_id',
        'cantidad', 'lote', 'fecha_caducidad',
        'precio_unitario',
    ];

    protected function casts(): array
    {
        return [
            'fecha_caducidad' => 'date',
            'precio_unitario' => 'decimal:2',
        ];
    }

    public function adquisicion(): BelongsTo
    {
        return $this->belongsTo(
            AdquisicionMedicamento::class, 'adquisicion_id'
        );
    }

    public function medicina(): BelongsTo
    {
        return $this->belongsTo(
            InventarioMedicina::class, 'inventario_medicina_id'
        );
    }
}
