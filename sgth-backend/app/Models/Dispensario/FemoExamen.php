<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoExamen extends Model
{
    protected $table = 'femo_examenes';

    protected $fillable = [
        'ficha_id', 'nombre_examen', 'resultado',
        'fecha_examen', 'tipo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_examen' => 'date',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
