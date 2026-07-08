<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoFactorRiesgo extends Model
{
    protected $table = 'femo_factores_riesgo';

    protected $fillable = [
        'ficha_id', 'categoria', 'factor',
        'presente', 'medida_preventiva',
    ];

    protected function casts(): array
    {
        return [
            'presente' => 'boolean',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
