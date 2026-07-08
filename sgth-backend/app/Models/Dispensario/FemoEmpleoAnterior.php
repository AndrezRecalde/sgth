<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoEmpleoAnterior extends Model
{
    protected $table = 'femo_empleos_anteriores';

    protected $fillable = [
        'ficha_id', 'centro_trabajo',
        'actividades_desempenadas',
        'fecha_inicio', 'fecha_fin', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
