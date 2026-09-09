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
        'receta_medica_id', 'inventario_medicina_id', 'medicamento_externo',
        'cantidad_prescrita',
        'cantidad_despachada', 'estado', 'dosis', 'frecuencia', 'duracion', 'observaciones'
    ];

    /**
     * Estado de un ítem que la farmacia no maneja.
     *
     * No es «pendiente»: no hay nada que esperar. El paciente lo adquiere
     * fuera, y el mostrador solo necesita saberlo para decírselo.
     */
    public const NO_DISPONIBLE = 'no_disponible';

    /**
     * Si lo recetado está fuera del catálogo de la farmacia.
     *
     * Se pregunta por la FK y no por el estado: el estado describe cómo va la
     * entrega, y esto es qué clase de ítem es. El CHECK de la tabla garantiza
     * que una cosa implica la otra.
     */
    public function esExterno(): bool
    {
        return $this->inventario_medicina_id === null;
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaMedica::class, 'receta_medica_id');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(InventarioMedicina::class, 'inventario_medicina_id');
    }
}
