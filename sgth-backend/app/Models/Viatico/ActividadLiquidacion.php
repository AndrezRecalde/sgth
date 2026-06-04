<?php
namespace App\Models\Viatico;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActividadLiquidacion extends Model
{
    protected $table = 'actividades_liquidacion';

    protected $fillable = [
        'liquidacion_viatico_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'descripcion',
        'lugar',
        'orden',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(
            LiquidacionViatico::class,
            'liquidacion_viatico_id'
        );
    }
}
