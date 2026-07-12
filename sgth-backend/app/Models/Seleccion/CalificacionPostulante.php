<?php

namespace App\Models\Seleccion;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalificacionPostulante extends Model
{
    protected $table = 'seleccion_calificaciones';

    protected $fillable = [
        'postulante_id', 'criterio_id', 'opcion_id',
        'valor_numerico', 'puntaje_obtenido',
        'observacion', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'valor_numerico'  => 'decimal:2',
            'puntaje_obtenido'=> 'decimal:2',
        ];
    }

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class);
    }

    public function criterio(): BelongsTo
    {
        return $this->belongsTo(CriterioEvaluacion::class, 'criterio_id');
    }

    public function opcion(): BelongsTo
    {
        return $this->belongsTo(OpcionCriterio::class, 'opcion_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
