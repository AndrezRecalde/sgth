<?php

namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Computador, portátil, monitor, proyector… con los años de vida útil del tipo. */
class TipoBien extends Model
{
    use SoftDeletes;

    protected $table = 'tipos_bien';

    protected $fillable = ['nombre', 'anios_vida_util'];

    public function subtipos(): HasMany
    {
        return $this->hasMany(SubtipoBien::class)->orderBy('nombre');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(BienInformatico::class);
    }
}
