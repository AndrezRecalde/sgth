<?php

namespace App\Models\Expediente;

use App\Enums\TipoCertificadoLaboral;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un certificado laboral que se emitió. Ver la migración para el porqué de la
 * bitácora.
 *
 * Sin SoftDeletes: una emisión ocurrió o no ocurrió. Si se borrara, el código
 * impreso en un papel que ya está en un banco dejaría de verificar.
 */
class EmisionCertificadoLaboral extends Model
{
    protected $table = 'emisiones_certificado_laboral';

    protected $fillable = [
        'codigo', 'servidor_id', 'tipo', 'con_remuneracion',
        'emitido_por', 'emitido_en', 'vence_en',
        'firmante_nombre', 'firmante_cargo', 'firmante_cedula',
        'datos',
    ];

    protected function casts(): array
    {
        return [
            'tipo'             => TipoCertificadoLaboral::class,
            'con_remuneracion' => 'boolean',
            'emitido_en'       => 'datetime',
            'vence_en'         => 'date',
            'datos'            => 'array',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    /** Un certificado vencido sigue existiendo: dejó de estar vigente, nada más. */
    public function estaVigente(): bool
    {
        return $this->vence_en->endOfDay()->isFuture();
    }
}
