<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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
}
