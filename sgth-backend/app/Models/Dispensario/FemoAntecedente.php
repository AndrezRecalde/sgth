<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoAntecedente extends Model
{
    protected $table = 'femo_antecedentes';

    protected $fillable = [
        'ficha_id', 'tipo', 'descripcion', 'fecha_aproximada',
    ];

    protected function casts(): array
    {
        return [
            'fecha_aproximada' => 'integer',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
