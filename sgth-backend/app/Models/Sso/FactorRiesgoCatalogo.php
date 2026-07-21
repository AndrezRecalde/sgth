<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\CategoriaFactorRiesgo;

class FactorRiesgoCatalogo extends Model
{
    use HasFactory;

    protected $table = 'factores_riesgo_catalogo';

    protected $fillable = [
        'nombre', 'categoria', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'categoria' => CategoriaFactorRiesgo::class,
            'activo' => 'boolean',
        ];
    }

    public function riesgosLaborales(): HasMany
    {
        return $this->hasMany(RiesgoLaboral::class, 'factor_riesgo_id');
    }
}
