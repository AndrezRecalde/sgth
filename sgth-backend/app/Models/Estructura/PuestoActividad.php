<?php

namespace App\Models\Estructura;

use App\Enums\FrecuenciaActividad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PuestoActividad extends Model
{
    protected $table = 'puesto_actividades';

    protected $fillable = [
        'puesto_id', 'descripcion', 'producto', 'conocimientos', 'frecuencia',
        'orden', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo'     => 'boolean',
            'orden'      => 'integer',
            'frecuencia' => FrecuenciaActividad::class,
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }
}
