<?php

namespace App\Models\Estructura;

use App\Observers\UnidadAdministrativaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(UnidadAdministrativaObserver::class)]
class UnidadAdministrativa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'unidades_administrativas';

    protected $fillable = [
        'codigo',
        'nombre',
        'acronimo',
        'descripcion',
        'tipo_unidad_id',
        'unidad_padre_id',
        'nivel',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'nivel'  => 'integer',
            'tipo_unidad_id' => 'string',
        ];
    }

    /**
     * Unidad administrativa jerárquicamente superior (padre).
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_padre_id');
    }

    /**
     * Tipo de unidad administrativa.
     */
    public function tipoUnidad(): BelongsTo
    {
        return $this->belongsTo(TipoUnidad::class, 'tipo_unidad_id');
    }

    /**
     * Unidades o subprocesos que dependen directamente de esta unidad (hijos).
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(UnidadAdministrativa::class, 'unidad_padre_id');
    }

    /**
     * Puestos orgánicos que pertenecen a esta unidad administrativa.
     */
    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class, 'unidad_administrativa_id');
    }
}
