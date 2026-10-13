<?php

namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dónde está físicamente un equipo: la sede y, si tiene, el piso. Sale de la
 * columna «PISO» del Excel de TIC (1ER…6TO y PB del edificio, y las sedes de
 * San Mateo, el Dispensario y UNAMYDESC).
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

    /** «Edificio principal · Piso 3», o solo la sede si no tiene piso. */
    protected function nombre(): Attribute
    {
        return Attribute::get(fn () => $this->piso ? "{$this->sede} · {$this->piso}" : $this->sede);
    }
}
