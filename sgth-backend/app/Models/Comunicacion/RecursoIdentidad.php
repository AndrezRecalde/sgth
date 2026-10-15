<?php

namespace App\Models\Comunicacion;

use App\Enums\OrigenRecursoIdentidad;
use App\Enums\VarianteIdentidad;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una versión de una variante del logo (docs/diseno-comunicacion-social.md,
 * 4.3). Rige desde `vigente_desde` hasta que empieza la siguiente; un
 * documento toma la vigente a su fecha. Nada se borra: una versión que ya rigió
 * queda en el historial, y solo una programada se puede anular.
 */
class RecursoIdentidad extends Model
{
    use LogsActivity;

    protected $table = 'recursos_identidad';

    protected $fillable = [
        'variante', 'archivo', 'mime', 'ancho', 'alto', 'peso', 'vigente_desde',
        'origen', 'reutiliza_id', 'nota', 'subido_por', 'anulada_en', 'anulada_por',
    ];

    protected function casts(): array
    {
        return [
            'variante'      => VarianteIdentidad::class,
            'origen'        => OrigenRecursoIdentidad::class,
            'vigente_desde' => 'date',
            'anulada_en'    => 'datetime',
            'ancho'         => 'integer',
            'alto'          => 'integer',
            'peso'          => 'integer',
        ];
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }

    public function reutiliza(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reutiliza_id');
    }

    /** Las que cuentan: una anulada no rige nunca. */
    public function scopeValidas(Builder $query): Builder
    {
        return $query->whereNull('anulada_en');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['variante', 'archivo', 'vigente_desde', 'origen', 'nota', 'anulada_en'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
