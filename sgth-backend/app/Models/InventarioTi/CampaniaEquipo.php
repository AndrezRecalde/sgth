<?php

namespace App\Models\InventarioTi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un equipo en una campaña de preventivo: pendiente, hecho (con su
 * mantenimiento) u omitido (con su motivo). Si el mantenimiento se anula
 * desde la ficha, vuelve a contar como pendiente.
 */
class CampaniaEquipo extends Model
{
    public const PENDIENTE = 'pendiente';

    public const HECHO = 'hecho';

    public const OMITIDO = 'omitido';

    protected $table = 'campania_equipos';

    protected $fillable = ['campania_id', 'bien_informatico_id', 'estado', 'mantenimiento_bien_id', 'motivo_omision', 'actualizado_por'];

    public function campania(): BelongsTo
    {
        return $this->belongsTo(CampaniaMantenimiento::class, 'campania_id');
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id')->withTrashed();
    }

    public function mantenimiento(): BelongsTo
    {
        return $this->belongsTo(MantenimientoBien::class, 'mantenimiento_bien_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** Hecho de verdad: con su mantenimiento sin anular. Uno anulado vuelve a pendiente. */
    public function estadoEfectivo(): string
    {
        if ($this->estado === self::HECHO && ($this->mantenimiento === null || $this->mantenimiento->anulado_en !== null)) {
            return self::PENDIENTE;
        }

        return $this->estado;
    }
}
