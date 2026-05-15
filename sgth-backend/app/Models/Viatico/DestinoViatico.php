<?php

namespace App\Models\Viatico;

use App\Models\Geografia\Ciudad;
use App\Models\Geografia\Provincia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestinoViatico extends Model
{
    use HasFactory;

    protected $table = 'destinos_viatico';

    protected $fillable = [
        'viatico_id',
        'tipo_destino',
        'provincia_id',
        'ciudad_id',
        'pais',
        'estado_region',
        'fecha_llegada',
        'fecha_salida',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'fecha_llegada' => 'date',
            'fecha_salida'  => 'date',
            'orden'         => 'integer',
        ];
    }

    /*
     * Validación de consistencia de destinos_viatico (Implementado en StoreDestinoViaticoRequest en Tarea 10):
     * 
     * Si tipo_destino = nacional:
     *   - provincia_id y ciudad_id son requeridos
     *   - pais y estado_region deben ser null
     * 
     * Si tipo_destino = internacional:
     *   - pais es requerido
     *   - provincia_id, ciudad_id y estado_region pueden ser null
     */

    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class);
    }

    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class);
    }

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
    }
}
