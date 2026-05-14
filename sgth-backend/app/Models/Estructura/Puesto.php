<?php

namespace App\Models\Estructura;

use App\Observers\PuestoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(PuestoObserver::class)]
class Puesto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'puestos';

    protected $fillable = [
        'codigo',
        'denominacion',
        'unidad_administrativa_id',
        'grupo_ocupacional',
        'grado_rmu',
        'rmu',
        'es_jefe',
        'nivel',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'es_jefe'   => 'boolean',
            'estado'    => 'boolean',
            'grado_rmu' => 'integer',
            'nivel'     => 'integer',
            'rmu'       => 'decimal:2',
        ];
    }

    /**
     * Unidad administrativa a la que pertenece orgánicamente este puesto.
     */
    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }
}
