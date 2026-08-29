<?php

namespace App\Models\Dispensario;

use App\Enums\CategoriaRiesgoLaboral;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoFactorRiesgo extends Model
{
    protected $table = 'femo_factores_riesgo';

    protected $fillable = [
        'ficha_id', 'ficha_actividad_id', 'categoria', 'subcategoria', 'factor',
        'presente', 'medida_preventiva',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaRiesgoLaboral::class,
            'presente' => 'boolean',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }

    public function fichaActividad(): BelongsTo
    {
        return $this->belongsTo(FemoFichaActividad::class, 'ficha_actividad_id');
    }
}
