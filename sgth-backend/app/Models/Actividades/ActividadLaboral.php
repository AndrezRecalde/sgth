<?php

namespace App\Models\Actividades;

use App\Enums\Actividades\CategoriaActividadEnum;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una actividad que el servidor anota en su portal: qué hizo, qué día y qué
 * produjo. Es suya: nadie más la ve ni la aprueba.
 */
class ActividadLaboral extends Model
{
    use SoftDeletes;

    protected $table = 'actividades_laborales';

    protected $fillable = [
        'servidor_id', 'fecha', 'descripcion', 'categoria', 'producto_entregable',
    ];

    protected function casts(): array
    {
        return [
            'fecha'     => 'date',
            'categoria' => CategoriaActividadEnum::class,
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id');
    }
}
