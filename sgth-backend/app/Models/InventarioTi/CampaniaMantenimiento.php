<?php

namespace App\Models\InventarioTi;

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una campaña de mantenimiento preventivo (fase 5.1): un lugar, un técnico,
 * un período y los equipos. Se ve programada, en curso, atrasada o cerrada
 * según las fechas y si se cerró.
 *
 * La del proveedor (5.1b) la hace la empresa de la vigencia tecnológica: lleva
 * su contrato, el técnico de TIC es opcional y, sin lugar, entran todos los
 * equipos del contrato.
 */
class CampaniaMantenimiento extends Model
{
    protected $table = 'campanias_mantenimiento';

    protected $fillable = [
        'nombre', 'descripcion', 'realizado_por', 'contrato_vigencia_id', 'ubicacion_id', 'unidad_administrativa_id', 'tecnico_id',
        'fecha_inicio', 'fecha_fin', 'cerrada', 'cerrada_por', 'cerrada_en', 'created_by',
    ];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'cerrada' => 'boolean', 'cerrada_en' => 'datetime'];
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(CampaniaEquipo::class, 'campania_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class)->withTrashed();
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoVigencia::class, 'contrato_vigencia_id')->withTrashed();
    }

    public function esDelProveedor(): bool
    {
        return $this->realizado_por === MantenimientoBien::POR_PROVEEDOR;
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    /** programada, en_curso, atrasada o cerrada. */
    public function situacion(): string
    {
        return match (true) {
            $this->cerrada => 'cerrada',
            $this->fecha_inicio->isFuture() => 'programada',
            $this->fecha_fin->lt(today()) => 'atrasada',
            default => 'en_curso',
        };
    }
}
