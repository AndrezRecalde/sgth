<?php
namespace App\Models\Dispensario;
use App\Enums\PresentacionMedicamento;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
     * Añade al listado lo que antes se leía de los campos de la ficha:
     * cuánto se puede entregar, cuánto está inmovilizado por vencido y qué
     * caducidad es la que manda.
     *
     * Van como subconsultas y no como `withSum` con restricción porque el
     * resultado tiene que poder compararse en un `where` —el bajo mínimo mira
     * el despachable— y un alias de `withSum` no está disponible ahí.
     */
    public function scopeConResumenDeLotes(Builder $query): Builder
    {
        $hoy = now()->toDateString();

        return $query->addSelect([
            'inventario_medicinas.*',

            'stock_despachable' => LoteMedicina::selectRaw(
                'COALESCE(SUM(stock_actual), 0)'
            )
                ->whereColumn('inventario_medicina_id', 'inventario_medicinas.id')
                ->where(fn ($q) => $q
                    ->whereNull('fecha_caducidad')
                    ->orWhereDate('fecha_caducidad', '>=', $hoy)),

            'stock_caducado' => LoteMedicina::selectRaw(
                'COALESCE(SUM(stock_actual), 0)'
            )
                ->whereColumn('inventario_medicina_id', 'inventario_medicinas.id')
                ->whereNotNull('fecha_caducidad')
                ->whereDate('fecha_caducidad', '<', $hoy),

            'proxima_caducidad' => LoteMedicina::select('fecha_caducidad')
                ->whereColumn('inventario_medicina_id', 'inventario_medicinas.id')
                ->where('stock_actual', '>', 0)
                ->orderByRaw('fecha_caducidad IS NULL')
                ->orderBy('fecha_caducidad')
                ->orderBy('id')
                ->limit(1),
        ]);
    }

    /**
     * Bajo mínimo se mide sobre lo que se puede entregar, no sobre el total.
     *
     * Ochenta unidades vencidas no son ochenta unidades: son cero para quien
     * viene a la ventanilla. Contándolas, una medicina agotada de hecho no
     * aparecía en la alerta de reposición porque su stock decía que estaba
     * llena.
     */
    public function scopeBajoMinimo(Builder $query): Builder
    {
        return $query->whereRaw(
            'COALESCE((
                SELECT SUM(l.stock_actual) FROM lotes_medicina l
                WHERE l.inventario_medicina_id = inventario_medicinas.id
                  AND (l.fecha_caducidad IS NULL OR l.fecha_caducidad >= ?)
            ), 0) <= inventario_medicinas.stock_minimo',
            [now()->toDateString()]
        );
    }

    /**
     * Una medicina sin fecha registrada no se considera caducada: no hay dato
     * que lo afirme, y bloquear por omisión dejaría la farmacia parada.
     *
     * El día de la caducidad todavía es válido, que es como se lee la fecha
     * impresa en el envase.
     *
     * Se responde sobre los lotes, no sobre el campo de la ficha: la pregunta
     * «¿está caducado este medicamento?» solo tiene sentido lote a lote, y
     * mirando un único campo la respuesta era la de la última entrada. Aquí
     * significa «no queda nada entregable»: todo lo que hay está vencido.
     */
    public function estaCaducado(): bool
    {
        return $this->lotes()->conStock()->exists()
            && ! $this->lotes()->conStock()->vigentes()->exists();
    }

    /** Unidades que se pueden entregar hoy: las de los lotes no vencidos. */
    public function stockDespachable(): int
    {
        return (int) $this->lotes()->vigentes()->sum('stock_actual');
    }

    /** Unidades inmovilizadas por vencidas, a la espera de darse de baja. */
    public function stockCaducado(): int
    {
        return (int) $this->lotes()->caducados()->sum('stock_actual');
    }
}
