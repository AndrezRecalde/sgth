<?php

namespace App\Models\InventarioTi;

use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dónde suele estar una unidad (decidido con TIC el 2026-10-13). Solo propone
 * la ubicación al registrar o entregar un equipo: la ubicación es del equipo,
 * y cambiar esta no mueve ninguno (para eso está la mudanza en lote).
 */
class UbicacionPorUnidad extends Model
{
    protected $table = 'ubicacion_por_unidad';

    protected $fillable = ['unidad_administrativa_id', 'ubicacion_id', 'updated_by'];

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }
}
