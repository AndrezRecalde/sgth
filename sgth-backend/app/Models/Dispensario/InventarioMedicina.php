<?php
namespace App\Models\Dispensario;
use App\Enums\PresentacionMedicamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\Dispensario\InventarioMedicinaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(InventarioMedicinaObserver::class)]
class InventarioMedicina extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventario_medicinas';

    protected $fillable = [
        'codigo', 'nombre', 'principio_activo', 'presentacion',
        'concentracion', 'stock_actual', 'stock_minimo', 'fecha_caducidad',
        'lote', 'estado', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'presentacion'    => PresentacionMedicamento::class,
            'fecha_caducidad' => 'date',
            'estado'          => 'boolean',
        ];
    }

    /**
     * Las existencias, repartidas en lotes con su caducidad propia.
     *
     * `stock_actual` de esta ficha es la suma de `lotes.stock_actual`; quien
     * la mueve es `StockPorLotes`, que mantiene el invariante.
     */
    public function lotes(): HasMany
    {
        return $this->hasMany(LoteMedicina::class, 'inventario_medicina_id');
    }

    /** La caducidad que manda: la del lote que sale primero por FEFO. */
    public function proximaCaducidad(): ?Carbon
    {
        return $this->lotes()->conStock()->fefo()->first()?->fecha_caducidad;
    }

    /**
     * Una medicina sin fecha registrada no se considera caducada: no hay dato
     * que lo afirme, y bloquear por omisión dejaría la farmacia parada.
     *
     * El día de la caducidad todavía es válido, que es como se lee la fecha
     * impresa en el envase.
     *
     * OJO: mira el campo de la ficha, que es el de la última entrada. Sigue
     * siendo lo que leen las alertas y el bloqueo de despacho, y por eso sigue
     * escribiéndose; son las lecturas de la segunda entrega las que pasarán a
     * mirar los lotes, donde la respuesta es exacta.
     */
    public function estaCaducado(): bool
    {
        return $this->fecha_caducidad !== null
            && $this->fecha_caducidad->startOfDay()->isBefore(now()->startOfDay());
    }
}
