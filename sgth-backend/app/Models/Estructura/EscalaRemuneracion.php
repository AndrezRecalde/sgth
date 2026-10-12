<?php

namespace App\Models\Estructura;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una escala de remuneraciones de la LOSEP (fase 7.3b): la resolución que fija
 * la RMU nueva de uno o varios grados, desde una fecha. Al registrarse escribe
 * la RMU en el catálogo y en cada vínculo de esos grados; revertirla devuelve
 * las dos.
 *
 * @property int $id
 * @property string $instrumento
 * @property \Illuminate\Support\Carbon $fecha_instrumento
 * @property \Illuminate\Support\Carbon $rige_desde
 * @property string $dictamen_presupuestario_ref
 * @property ?string $descripcion
 * @property int $registrada_por
 * @property \Illuminate\Support\Carbon $registrada_en
 * @property ?int $revertida_por
 * @property ?\Illuminate\Support\Carbon $revertida_en
 * @property ?string $motivo_reversion
 */
class EscalaRemuneracion extends Model
{
    protected $table = 'escalas_remuneracion';

    protected $fillable = [
        'instrumento', 'fecha_instrumento', 'rige_desde', 'dictamen_presupuestario_ref', 'descripcion',
        'registrada_por', 'registrada_en', 'revertida_por', 'revertida_en', 'motivo_reversion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_instrumento' => 'date',
            'rige_desde'        => 'date',
            'registrada_en'     => 'datetime',
            'revertida_en'      => 'datetime',
        ];
    }

    public function estaRevertida(): bool
    {
        return $this->revertida_en !== null;
    }

    public function grados(): HasMany
    {
        return $this->hasMany(EscalaRemuneracionGrado::class, 'escala_id');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(EscalaRemuneracionVinculo::class, 'escala_id');
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    public function revertidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revertida_por');
    }
}
