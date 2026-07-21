<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Observers\Sso\EquipoProteccionObserver;

#[ObservedBy(EquipoProteccionObserver::class)]
class EquipoProteccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipos_proteccion';

    protected $fillable = [
        'codigo', 'nombre', 'tipo', 'norma_tecnica',
        'vida_util_meses', 'estado', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function asignacionesPuesto(): HasMany
    {
        return $this->hasMany(PuestoEpp::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EppEntrega::class);
    }
}
