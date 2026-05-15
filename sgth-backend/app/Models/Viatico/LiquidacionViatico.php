<?php

namespace App\Models\Viatico;

use App\Models\User;
use App\Observers\Viatico\LiquidacionViaticoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(LiquidacionViaticoObserver::class)]
class LiquidacionViatico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'liquidaciones_viatico';

    protected $fillable = [
        'viatico_id',
        'facturas',
        'total_facturas',
        'monto_justificado',
        'diferencia_devolver',
        'fecha_retorno',
        'fecha_liquidacion',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'facturas'            => 'array',
            'total_facturas'      => 'decimal:2',
            'monto_justificado'   => 'decimal:2',
            'diferencia_devolver' => 'decimal:2',
            'fecha_retorno'       => 'date',
            'fecha_liquidacion'   => 'date',
        ];
    }

    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class, 'viatico_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function detallesFactura(): HasMany
    {
        return $this->hasMany(FacturaViatico::class, 'liquidacion_viatico_id');
    }
}
