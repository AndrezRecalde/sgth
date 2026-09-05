<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class MovimientoInventarioMed extends Model
{
    use HasFactory;
    // SIN SoftDeletes, inmutable

    protected $table = 'movimientos_inventario_med';

    protected $fillable = [
        'lote_id',
        'inventario_medicina_id', 'tipo_movimiento', 'cantidad',
        'stock_resultante', 'motivo', 'referencia_receta_id', 'registrado_por'
    ];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(InventarioMedicina::class, 'inventario_medicina_id');
    }

    /** Null en los movimientos anteriores al control por lotes. */
    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteMedicina::class, 'lote_id');
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaMedica::class, 'referencia_receta_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
