<?php

namespace App\Models\Helpdesk;

use App\Enums\PrioridadTicket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un problema frecuente de «Pedir ayuda a TIC»: el servidor lo elige y el
 * ticket sale con el asunto (su nombre), la descripción, la categoría, la
 * prioridad y el tipo que fijó TIC. La subunidad y el plazo los da la
 * categoría. No se borra: se desactiva.
 *
 * @property int $id
 * @property string $nombre
 * @property int $categoria_id
 * @property string $tipo_ticket
 * @property PrioridadTicket $prioridad
 * @property string $descripcion
 * @property int $orden
 * @property bool $activo
 */
class ProblemaFrecuente extends Model
{
    protected $table = 'problemas_frecuentes';

    public const TIPOS = ['incidente', 'solicitud'];

    protected $fillable = ['nombre', 'categoria_id', 'tipo_ticket', 'prioridad', 'descripcion', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['prioridad' => PrioridadTicket::class, 'orden' => 'integer', 'activo' => 'boolean'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaTicket::class, 'categoria_id')->withTrashed();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
