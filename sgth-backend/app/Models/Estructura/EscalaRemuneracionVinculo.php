<?php

namespace App\Models\Estructura;

use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que la escala le hizo a un vínculo (fase 7.3b): la remuneración que tenía
 * y la que le dejó. Solo están los que cambiaron: quien ya cobraba la RMU nueva
 * o más, no baja ni sube.
 *
 * @property int $id
 * @property int $escala_id
 * @property int $contrato_id
 * @property int $servidor_id
 * @property int $grupo_ocupacional_id
 * @property ?string $remuneracion_anterior
 * @property string $remuneracion_nueva
 */
class EscalaRemuneracionVinculo extends Model
{
    protected $table = 'escala_remuneracion_vinculos';

    protected $fillable = [
        'escala_id', 'contrato_id', 'servidor_id', 'grupo_ocupacional_id', 'remuneracion_anterior', 'remuneracion_nueva',
    ];

    protected function casts(): array
    {
        return [
            'remuneracion_anterior' => 'decimal:2',
            'remuneracion_nueva'    => 'decimal:2',
        ];
    }

    public function escala(): BelongsTo
    {
        return $this->belongsTo(EscalaRemuneracion::class, 'escala_id');
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoServidor::class, 'contrato_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function grupoOcupacional(): BelongsTo
    {
        return $this->belongsTo(GrupoOcupacional::class);
    }
}
