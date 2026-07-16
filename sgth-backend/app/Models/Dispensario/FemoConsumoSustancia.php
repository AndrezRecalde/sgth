<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoConsumoSustancia extends Model
{
    protected $table = 'femo_consumo_sustancias';

    protected $fillable = [
        'ficha_id', 'sustancia', 'sustancia_otra_detalle',
        'tiempo_consumo_meses', 'ex_consumidor',
        'tiempo_abstinencia_meses', 'no_consume',
    ];

    protected function casts(): array
    {
        return [
            'ex_consumidor' => 'boolean',
            'no_consume' => 'boolean',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
