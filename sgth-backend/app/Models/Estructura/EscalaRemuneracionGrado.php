<?php

namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La RMU de un grado antes y después de la escala (fase 7.3b).
 *
 * @property int $id
 * @property int $escala_id
 * @property int $grupo_ocupacional_id
 * @property string $rmu_anterior
 * @property string $rmu_nueva
 */
class EscalaRemuneracionGrado extends Model
{
    protected $table = 'escala_remuneracion_grados';

    protected $fillable = ['escala_id', 'grupo_ocupacional_id', 'rmu_anterior', 'rmu_nueva'];

    protected function casts(): array
    {
        return [
            'rmu_anterior' => 'decimal:2',
            'rmu_nueva'    => 'decimal:2',
        ];
    }

    public function escala(): BelongsTo
    {
        return $this->belongsTo(EscalaRemuneracion::class, 'escala_id');
    }

    public function grupoOcupacional(): BelongsTo
    {
        return $this->belongsTo(GrupoOcupacional::class);
    }
}
