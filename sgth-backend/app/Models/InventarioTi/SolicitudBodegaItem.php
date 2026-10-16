<?php

namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un renglón de la solicitud a bodega: el consumible, cuánto se pidió y cuánto despachó Bodega. */
class SolicitudBodegaItem extends Model
{
    protected $table = 'solicitud_bodega_items';

    protected $fillable = ['solicitud_id', 'consumible_id', 'cantidad_solicitada', 'cantidad_despachada'];

    protected function casts(): array
    {
        return ['cantidad_solicitada' => 'integer', 'cantidad_despachada' => 'integer'];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudBodega::class, 'solicitud_id');
    }

    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class);
    }
}
