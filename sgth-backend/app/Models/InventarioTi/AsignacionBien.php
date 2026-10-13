<?php

namespace App\Models\InventarioTi;

use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La custodia de un equipo: quién responde por él (`servidor_id`, el que firma
 * el acta), quién lo usa si es otra persona (`usuario_servidor_id`) y desde
 * cuándo hasta cuándo. Un traspaso cierra una y abre la siguiente, enlazada
 * por `asignacion_anterior_id`.
 */
class AsignacionBien extends Model
{
    use HasFactory, SoftDeletes;

    public const ESTADO_ACTIVA = 'activa';

    public const ESTADO_DEVUELTA = 'devuelta';

    public const MOTIVO_ENTREGA = 'entrega';

    public const MOTIVO_TRASPASO = 'traspaso';

    protected $table = 'asignaciones_bien';

    protected $fillable = [
        'bien_informatico_id', 'servidor_id', 'usuario_servidor_id',
        'fecha_asignacion', 'fecha_devolucion', 'observaciones', 'estado',
        'motivo', 'asignacion_anterior_id',
        'memorando_numero', 'memorando_archivo', 'memorando_nombre', 'soporte_anterior',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'date',
            'fecha_devolucion' => 'date',
        ];
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    /** El custodio: quien firma el acta y responde por el equipo. */
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    /** Quien usa el equipo, cuando no es el custodio. */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'usuario_servidor_id');
    }

    /** La custodia a la que reemplazó, en un traspaso. */
    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'asignacion_anterior_id');
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estaVigente(): bool
    {
        return $this->estado === self::ESTADO_ACTIVA && $this->fecha_devolucion === null;
    }
}
