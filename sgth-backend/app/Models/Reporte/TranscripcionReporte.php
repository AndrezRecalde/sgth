<?php

namespace App\Models\Reporte;

use App\Models\Expediente\MovimientoPersonal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un acto ya transcrito al portal del Ministerio, el SIITH o el SUT (fase 7.5).
 * Si se anula después, también se anula allá: queda dicho cuándo.
 *
 * @property int $id
 * @property int $movimiento_id
 * @property string $portal
 * @property int $transcrito_por
 * @property \Illuminate\Support\Carbon $transcrito_en
 * @property ?int $anulacion_transcrita_por
 * @property ?\Illuminate\Support\Carbon $anulacion_transcrita_en
 */
class TranscripcionReporte extends Model
{
    protected $table = 'transcripciones_reporte';

    protected $fillable = [
        'movimiento_id', 'portal', 'transcrito_por', 'transcrito_en', 'anulacion_transcrita_por', 'anulacion_transcrita_en',
    ];

    protected function casts(): array
    {
        return [
            'transcrito_en'           => 'datetime',
            'anulacion_transcrita_en' => 'datetime',
        ];
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_id');
    }

    public function transcritoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transcrito_por');
    }
}
