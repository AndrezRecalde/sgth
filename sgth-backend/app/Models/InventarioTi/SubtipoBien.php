<?php

namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** La forma del equipo dentro de su tipo: un computador «Todo en uno», «CPU»… */
class SubtipoBien extends Model
{
    use SoftDeletes;

    protected $table = 'subtipos_bien';

    protected $fillable = ['tipo_bien_id', 'nombre'];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoBien::class, 'tipo_bien_id');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(BienInformatico::class);
    }
}
