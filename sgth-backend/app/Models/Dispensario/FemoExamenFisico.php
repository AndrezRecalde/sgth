<?php

namespace App\Models\Dispensario;

use App\Enums\RegionExamenFisico;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoExamenFisico extends Model
{
    protected $table = 'femo_examen_fisico';

    protected $fillable = [
        'ficha_id', 'region', 'item', 'normal', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'region' => RegionExamenFisico::class,
            'normal' => 'boolean',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
