<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un lote de una medicina: lo que entró de una vez, con su caducidad propia.
 *
 * Sin SoftDeletes: un lote agotado se queda con stock cero, porque es lo que
 * explica los movimientos que salieron de él.
 */
class LoteMedicina extends Model
{
    use HasFactory;

    protected $table = 'lotes_medicina';

    protected $fillable = [
        'inventario_medicina_id', 'item_adquisicion_id',
        'codigo_lote', 'fecha_caducidad',
        'cantidad_ingresada', 'stock_actual',
    ];

    protected function casts(): array
    {
        return [
            'fecha_caducidad'    => 'date',
            'cantidad_ingresada' => 'integer',
            'stock_actual'       => 'integer',
        ];
    }

    public function medicina(): BelongsTo
    {
        return $this->belongsTo(
            InventarioMedicina::class, 'inventario_medicina_id'
        );
    }

    public function itemAdquisicion(): BelongsTo
    {
        return $this->belongsTo(
            ItemAdquisicion::class, 'item_adquisicion_id'
        );
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventarioMed::class, 'lote_id');
    }

    /**
     * Sin fecha registrada no se considera caducado: no hay dato que lo
     * afirme. El día impreso en el envase todavía es válido. Misma regla que
     * traía `InventarioMedicina::estaCaducado()`, ahora donde corresponde.
     */
    public function estaCaducado(): bool
    {
        return $this->fecha_caducidad !== null
            && $this->fecha_caducidad->startOfDay()
                ->isBefore(now()->startOfDay());
    }

    /** Los lotes que la migración inicial abrió porque nadie anotó cuál era. */
    public function estaIdentificado(): bool
    {
        return $this->codigo_lote !== null;
    }

    public function getEtiquetaAttribute(): string
    {
        return $this->codigo_lote ?? 'Sin identificar';
    }

    public function scopeConStock(Builder $query): Builder
    {
        return $query->where('stock_actual', '>', 0);
    }

    /**
     * First Expired, First Out: sale antes lo que caduca antes.
     *
     * Los lotes sin fecha van al final —no se puede afirmar que caduquen
     * pronto— y a igualdad de fecha manda el orden de entrada, que es lo más
     * parecido a lo que hay delante en el estante.
     */
    public function scopeFefo(Builder $query): Builder
    {
        return $query
            ->orderByRaw('fecha_caducidad IS NULL')
            ->orderBy('fecha_caducidad')
            ->orderBy('id');
    }
}
