<?php

namespace App\Models\InventarioTi;

use App\Enums\EstadoPrestamo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un cambio de estado de un préstamo: de cuál a cuál, quién y cuándo. */
class HistorialEstadoPrestamo extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'historial_estados_prestamo';

    protected $fillable = ['prestamo_equipo_id', 'estado_anterior', 'estado_nuevo', 'user_id', 'detalle'];

    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoPrestamo::class,
            'estado_nuevo'    => EstadoPrestamo::class,
            'created_at'      => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
