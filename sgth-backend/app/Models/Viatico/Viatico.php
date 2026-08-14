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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ViaticoObserver::class)]
class Viatico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'viaticos';

    protected $fillable = [
        'codigo_viatico',
        'servidor_id',
        'zona',
        'fecha_solicitud',
        'datetime_salida',
        'datetime_llegada',
        'total_dias',
        'coeficiente_exterior',
        'motivo_rechazo',
        'justificacion',
        'estado',
        'monto_calculado',
        'monto_anticipo',
        'modalidad_anticipo',
        'tipo_viaje',
        'pais_destino',
        'numero_resolucion',
        'partida_presupuestaria',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'zona'             => ZonaViatico::class,
            'estado'           => EstadoViatico::class,
            'datetime_salida'  => 'datetime',
            'datetime_llegada' => 'datetime',
            'monto_calculado'  => 'decimal:2',
            'monto_anticipo'   => 'decimal:2',
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

    public function tramos(): HasMany
    {
        return $this->hasMany(TramoViatico::class)
                    ->orderBy('orden');
    }

    public function autorizacionesVuelo(): HasMany
    {
        return $this->hasMany(AutorizacionVuelo::class);
    }

    public function tieneAutorizacionesPendientes(): bool
    {
        return $this->autorizacionesVuelo()
            ->where('estado', 'pendiente')
            ->exists();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function servidoresAcompanantes(): HasMany
    {
        return $this->hasMany(ViaticoServidor::class)
                    ->where('es_titular', false);
    }

    public function todosServidores(): HasMany
    {
        return $this->hasMany(ViaticoServidor::class);
    }
}
