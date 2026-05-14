<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemReceta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'items_receta';

    protected $fillable = [
        'receta_medica_id', 'inventario_medicina_id', 'cantidad_prescrita',
        'cantidad_despachada', 'dosis', 'frecuencia', 'duracion', 'observaciones'
    ];

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaMedica::class, 'receta_medica_id');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(InventarioMedicina::class, 'inventario_medicina_id');
    }
}
