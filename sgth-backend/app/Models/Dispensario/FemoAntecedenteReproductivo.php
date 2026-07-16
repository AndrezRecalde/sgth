<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoAntecedenteReproductivo extends Model
{
    protected $table = 'femo_antecedentes_reproductivos';

    protected $fillable = [
        'ficha_id', 'fecha_ultima_menstruacion',
        'gestas', 'partos', 'cesareas', 'abortos',
        'usa_metodo_planificacion', 'metodo_planificacion_cual',
        'examenes_realizados', 'examenes_tiempo_anios',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ultima_menstruacion' => 'date',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
