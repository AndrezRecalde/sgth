<?php

namespace App\Models\Dispensario;

use App\Models\Estructura\PuestoActividad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FemoFichaActividad extends Model
{
    protected $table = 'femo_ficha_actividades';

    protected $fillable = [
        'ficha_id', 'puesto_actividad_id', 'actividad',
        'medida_preventiva', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }

    public function puestoActividad(): BelongsTo
    {
        return $this->belongsTo(PuestoActividad::class);
    }

    public function factoresRiesgo(): HasMany
    {
        return $this->hasMany(FemoFactorRiesgo::class, 'ficha_actividad_id');
    }
}
