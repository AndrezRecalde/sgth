<?php

namespace App\Models\Nomina;

use App\Models\Expediente\Servidor;
use App\Observers\Nomina\DetalleNominaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(DetalleNominaObserver::class)]
class DetalleNomina extends Model
{
    use HasFactory;
    
    // Registro inmutable: sin SoftDeletes

    protected $table = 'detalle_nomina';

    protected $fillable = [
        'nomina_id',
        'servidor_id',
        'concepto_nomina_id',
        // La cuota de un préstamo: se cuenta como pagada al cerrar (fase 7.3c).
        'descuento_recurrente_id',
        'valor',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'float',
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoNomina::class, 'concepto_nomina_id');
    }

    public function descuentoRecurrente(): BelongsTo
    {
        return $this->belongsTo(DescuentoRecurrente::class);
    }
}
