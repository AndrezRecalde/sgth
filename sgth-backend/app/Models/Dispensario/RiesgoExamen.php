<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un examen que corresponde a un factor de riesgo del puesto (D6). */
class RiesgoExamen extends Model
{
    protected $table = 'riesgo_examenes';

    protected $fillable = ['categoria', 'factor', 'catalogo_examen_id'];

    public function examen(): BelongsTo
    {
        return $this->belongsTo(CatalogoExamen::class, 'catalogo_examen_id');
    }
}
