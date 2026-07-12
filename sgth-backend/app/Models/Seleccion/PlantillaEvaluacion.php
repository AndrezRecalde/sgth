<?php

namespace App\Models\Seleccion;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantillaEvaluacion extends Model
{
    protected $table = 'plantillas_evaluacion';

    protected $fillable = [
        'nombre', 'descripcion',
        'tipo_contrato', 'activa', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    public function criterios(): HasMany
    {
        return $this->hasMany(PlantillaCriterio::class, 'plantilla_id')
            ->orderBy('seccion')
            ->orderBy('orden');
    }

    public function criteriosMeritos(): HasMany
    {
        return $this->hasMany(PlantillaCriterio::class, 'plantilla_id')
            ->where('seccion', 'meritos')
            ->orderBy('orden');
    }

    public function criteriosOposicion(): HasMany
    {
        return $this->hasMany(PlantillaCriterio::class, 'plantilla_id')
            ->where('seccion', 'oposicion')
            ->orderBy('orden');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
