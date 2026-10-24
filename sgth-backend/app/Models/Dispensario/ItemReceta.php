<?php
namespace App\Models\Dispensario;

use App\Models\User;
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
        'cantidad_despachada', 'estado', 'dosis', 'frecuencia', 'duracion', 'observaciones',
        'motivo_no_entregado', 'detalle_no_entregado', 'no_entregado_por', 'no_entregado_en',
    ];

    protected function casts(): array
    {
        return [
            'no_entregado_en' => 'datetime',
        ];
    }

    /**
     * Estado de un ítem que la farmacia no maneja.
     *
     * No es «pendiente»: no hay nada que esperar. El paciente lo adquiere
     * fuera, y el mostrador solo necesita saberlo para decírselo.
     */
    public const NO_DISPONIBLE = 'no_disponible';

    /**
     * Estado de un ítem del inventario que el mostrador no va a entregar
     * (agotado, vencido u otro): el paciente lo adquiere fuera. Lo entregado
     * antes, si lo hubo, sigue en `cantidad_despachada`.
     */
    public const NO_ENTREGADO = 'no_entregado';

    /**
     * Si lo recetado está fuera del catálogo de la farmacia.
     *
     * Se pregunta por la FK y no por el estado: el estado describe cómo va la
     * entrega, y esto es qué clase de ítem es. Que un externo nazca
     * `no_disponible` lo pone el servicio al emitirlo; el CHECK de la tabla
     * solo garantiza que haya FK o nombre, exactamente uno.
     */
    public function esExterno(): bool
    {
        return $this->inventario_medicina_id === null;
    }

    public function receta(): BelongsTo
    {
        return $this->belongsTo(RecetaMedica::class, 'receta_medica_id');
    }

    public function noEntregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'no_entregado_por');
    }

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(InventarioMedicina::class, 'inventario_medicina_id');
    }
}
