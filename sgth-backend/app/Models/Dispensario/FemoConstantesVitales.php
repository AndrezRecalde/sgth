<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoConstantesVitales extends Model
{
    protected $table = 'femo_constantes_vitales';

    protected $fillable = [
        'ficha_id', 'temperatura_c',
        'presion_sistolica', 'presion_diastolica',
        'frecuencia_cardiaca', 'frecuencia_respiratoria',
        'saturacion_oxigeno', 'peso_kg', 'talla_cm',
        'imc', 'glucosa',
    ];

    protected function casts(): array
    {
        return [
            'temperatura_c'        => 'decimal:1',
            'saturacion_oxigeno'   => 'decimal:1',
            'peso_kg'              => 'decimal:2',
            'talla_cm'             => 'decimal:2',
            'imc'                  => 'decimal:2',
            'glucosa'              => 'decimal:1',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }
}
