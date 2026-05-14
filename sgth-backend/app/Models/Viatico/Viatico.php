<?php

namespace App\Models\Viatico;

use App\Enums\EstadoViatico;
use App\Enums\ZonaViatico;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Viatico\ViaticoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ViaticoObserver::class)]
class Viatico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'viaticos';

    protected $fillable = [
        'servidor_id',
        'zona',
        'tipo',
        'destino',
        'fecha_inicio',
        'fecha_fin',
        'justificacion',
        'estado',
        'monto_calculado',
        'monto_anticipo',
        'numero_resolucion',
        'partida_presupuestaria',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'zona'            => ZonaViatico::class,
            'estado'          => EstadoViatico::class,
            'fecha_inicio'    => 'datetime',
            'fecha_fin'       => 'datetime',
            'monto_calculado' => 'decimal:2',
            'monto_anticipo'  => 'decimal:2',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function liquidacion(): HasOne
    {
        return $this->hasOne(LiquidacionViatico::class, 'viatico_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
