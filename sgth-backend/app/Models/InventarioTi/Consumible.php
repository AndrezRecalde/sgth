<?php

namespace App\Models\InventarioTi;

use App\Enums\ColorConsumible;
use App\Enums\PresentacionConsumible;
use App\Enums\TipoConsumible;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un consumible del catálogo de TIC (fase 5.4a): tóner, tinta, cartucho,
 * cinta, unidad de imagen, kit o repuesto informático.
 *
 * El saldo no se escribe: es la suma de los movimientos vivos que lo afectan,
 * y solo existe si lo guarda TIC (`guarda_tic`). Lo que despacha Bodega
 * registra sus entregas sin saldo [P4].
 */
class Consumible extends Model
{
    protected $table = 'consumibles';

    protected $fillable = [
        'codigo_bodega', 'descripcion', 'tipo', 'color', 'presentacion',
        'guarda_tic', 'stock_minimo', 'activo', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tipo'         => TipoConsumible::class,
            'color'        => ColorConsumible::class,
            'presentacion' => PresentacionConsumible::class,
            'guarda_tic'   => 'boolean',
            'stock_minimo' => 'integer',
            'activo'       => 'boolean',
        ];
    }

    public function modelos(): HasMany
    {
        return $this->hasMany(ConsumibleModelo::class)->orderBy('modelo_normalizado');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoConsumible::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Suma el saldo como `saldo` (lo que cuenta: vivo y que afecta el saldo). */
    public function scopeConSaldo(Builder $q): Builder
    {
        return $q->withSum(['movimientos as saldo' => fn (Builder $m) => $m->where('afecta_saldo', true)->whereNull('anulado_en')], 'cantidad');
    }

    /** Los que sirven al equipo: misma marca y mismo modelo, sin mayúsculas ni espacios. */
    public function scopeCompatiblesCon(Builder $q, ?int $marcaId, ?string $modelo): Builder
    {
        $normalizado = ConsumibleModelo::normalizar($modelo);

        return $q->whereHas('modelos', fn (Builder $m) => $m->where('marca_id', $marcaId)->where('modelo_normalizado', $normalizado));
    }

    /** El saldo de ahora; null si no lo guarda TIC. */
    public function saldoActual(): ?int
    {
        if (! $this->guarda_tic) {
            return null;
        }

        return (int) $this->movimientos()->where('afecta_saldo', true)->whereNull('anulado_en')->sum('cantidad');
    }

    /** Por debajo del mínimo: solo lo que guarda TIC, con un mínimo puesto y activo. */
    public function bajoMinimo(?int $saldo): bool
    {
        return $this->guarda_tic && $this->activo && $this->stock_minimo > 0 && $saldo !== null && $saldo < $this->stock_minimo;
    }
}
