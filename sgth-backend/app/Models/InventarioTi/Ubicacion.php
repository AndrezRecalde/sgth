<?php

namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dónde está físicamente un equipo: la sede y, si tiene, el piso. Las sedes
 * son el Edificio Central (planta baja y pisos 1 a 6), el Dispensario Médico
 * y San Mateo; TIC agrega las que hagan falta desde Configuración. Es un dato
 * del equipo y no de la unidad: las unidades pueden cambiar de piso
 * (`UbicacionesBienService`).
 */
class Ubicacion extends Model
{
    use SoftDeletes;

    protected $table = 'ubicaciones';

    protected $fillable = ['sede', 'piso', 'descripcion'];

    public function bienes(): HasMany
    {
        return $this->hasMany(BienInformatico::class);
    }

    /**
     * Por sede y, dentro de cada una, primero la sede sin piso, luego la planta
     * baja y los pisos en orden («Piso 10» después de «Piso 9»).
     */
    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('sede')
            ->orderByRaw('piso IS NOT NULL')
            ->orderByRaw("lower(coalesce(piso, '')) <> 'planta baja'")
            ->orderByRaw('length(piso)')
            ->orderBy('piso');
    }

    /** «Edificio Central · Piso 3», o solo la sede si no tiene piso. */
    protected function nombre(): Attribute
    {
        return Attribute::get(fn () => $this->piso ? "{$this->sede} · {$this->piso}" : $this->sede);
    }
}
