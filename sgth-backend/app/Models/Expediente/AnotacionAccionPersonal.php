<?php

namespace App\Models\Expediente;

use App\Enums\TipoAnotacionAccion;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\VistoBueno;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que se le anota a una acción de personal después de emitida, en vez de
 * reescribirla (ver TipoAnotacionAccion).
 *
 * Tampoco se corrige: una anotación equivocada se aclara con otra.
 */
class AnotacionAccionPersonal extends Model
{
    protected $table = 'anotaciones_accion_personal';

    protected $fillable = [
        'movimiento_personal_id',
        'tipo',
        'texto',
        'visto_bueno_id',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoAnotacionAccion::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new ReglaNegocioException(
                'Una anotación no se edita: si algo quedó mal, anote otra que lo aclare.'
            );
        });
    }

    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class);
    }

    public function vistoBueno(): BelongsTo
    {
        return $this->belongsTo(VistoBueno::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
