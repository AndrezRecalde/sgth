<?php

namespace App\Models\Expediente;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Actos que nacen juntos (diseño de Acciones de Personal, 8.5; fase 4.6). Hoy
 * el incremento de remuneración de los obreros, por la tabla sectorial o el
 * contrato colectivo: un acto por obrero, cada uno con su hoja de registro
 * interno. Desde la fase 7.1a, el traspaso de un puesto: un acto por ocupante,
 * que se registran y se anulan juntos. En la 7.1b, la revisión de clasificación.
 *
 * El lote no reemplaza a sus actos: cada uno se tramita y se anula por sí
 * mismo. El lote guarda lo común —el documento de origen, el dictamen, la
 * fecha— y desde él se suscriben y registran todos de una vez.
 */
class LoteAccion extends Model
{
    use LogsActivity;

    public const INCREMENTO_OBREROS = 'incremento_obreros';

    public const TRASPASO_PUESTO = 'traspaso_puesto';

    protected $table = 'lotes_accion';

    protected $fillable = [
        'tipo',
        'descripcion',
        'fecha_efectiva',
        'documento_origen',
        'dictamen_presupuestario_ref',
        'parametros',
        'contrato_colectivo_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_efectiva' => 'date',
            'parametros'     => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tipo', 'descripcion', 'fecha_efectiva', 'documento_origen', 'dictamen_presupuestario_ref'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPersonal::class, 'lote_id');
    }

    public function contratoColectivo(): BelongsTo
    {
        return $this->belongsTo(ContratoColectivo::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
