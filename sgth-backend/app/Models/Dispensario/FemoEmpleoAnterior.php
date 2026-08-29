<?php

namespace App\Models\Dispensario;

use App\Enums\TipoEventoLaboral;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoEmpleoAnterior extends Model
{
    protected $table = 'femo_empleos_anteriores';

    protected $fillable = [
        'ficha_id', 'centro_trabajo',
        'actividades_desempenadas', 'es_trabajo_actual',
        'fecha_inicio', 'fecha_fin', 'observaciones',
        'tipo_evento_laboral', 'calificado_iess',
        'fecha_evento', 'especificar',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'tipo_evento_laboral' => TipoEventoLaboral::class,
            'calificado_iess' => 'boolean',
            'es_trabajo_actual' => 'boolean',
            'fecha_evento' => 'date',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
