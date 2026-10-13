<?php

namespace App\Models\InventarioTi;

use App\Enums\EstadoPrestamo;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un préstamo de equipos (docs/diseno-subsistema-tic.md, 4.6): quién lo pide,
 * para qué y dónde, las horas previstas y las reales, y su trámite.
 */
class PrestamoEquipo extends Model
{
    protected $table = 'prestamos_equipo';

    protected $fillable = [
        'folio', 'servidor_id', 'unidad_administrativa_id', 'estado', 'motivo', 'lugar_uso', 'fuera_del_edificio',
        'inicio_previsto', 'fin_previsto', 'directo', 'registrado_por',
        'aprobado_por', 'aprobado_en', 'rechazado_por', 'rechazado_en', 'motivo_rechazo',
        'cancelado_por', 'cancelado_en', 'motivo_cancelacion',
        'entregado_por', 'entregado_en', 'recibido_por', 'devuelto_en', 'observaciones',
        'recordatorio_enviado_en', 'aviso_vencido_en',
    ];

    protected function casts(): array
    {
        return [
            'estado'             => EstadoPrestamo::class,
            'fuera_del_edificio' => 'boolean',
            'directo'            => 'boolean',
            'inicio_previsto'    => 'datetime',
            'fin_previsto'       => 'datetime',
            'aprobado_en'        => 'datetime',
            'rechazado_en'       => 'datetime',
            'cancelado_en'       => 'datetime',
            'entregado_en'       => 'datetime',
            'devuelto_en'        => 'datetime',
            'recordatorio_enviado_en' => 'datetime',
            'aviso_vencido_en'   => 'datetime',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrestamoItem::class)->orderBy('id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialEstadoPrestamo::class)->orderBy('id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }

    public function canceladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelado_por');
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por');
    }

    /** En curso y con la hora de devolución pasada. No es un estado: se calcula. */
    public function vencido(): bool
    {
        return $this->estado === EstadoPrestamo::EN_CURSO && $this->fin_previsto->isPast();
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query->where('estado', EstadoPrestamo::EN_CURSO)->where('fin_previsto', '<', now());
    }
}
