<?php

namespace App\Models\Viatico;

use App\Models\Geografia\Ciudad;
use App\Models\Geografia\Provincia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransporteViatico extends Model
{
    use HasFactory;

    protected $table = 'transportes_viatico';

    protected $fillable = [
        'viatico_id',
        'tipo',
        'provincia_origen_id',
        'ciudad_origen_id',
        'provincia_destino_id',
        'ciudad_destino_id',
        'pais_origen',
        'pais_destino',
        'fecha_viaje',
        'empresa_o_aerolinea',
        'numero_ticket_o_billete',
        'placa_vehiculo',
        'kilometraje',
        'valor_kilometro',
        'monto',
        'archivo_respaldo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_viaje'     => 'datetime',
            'kilometraje'     => 'decimal:2',
            'valor_kilometro' => 'decimal:2',
            'monto'           => 'decimal:2',
        ];
    }

    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class);
    }

    public function provinciaOrigen(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_origen_id');
    }

    public function ciudadOrigen(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_origen_id');
    }

    public function provinciaDestino(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_destino_id');
    }

    public function ciudadDestino(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_destino_id');
    }
}
