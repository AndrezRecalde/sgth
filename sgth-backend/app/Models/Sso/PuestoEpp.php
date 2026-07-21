<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Estructura\Puesto;

class PuestoEpp extends Model
{
    protected $table = 'puesto_epp';

    protected $fillable = [
        'puesto_id', 'equipo_proteccion_id', 'cantidad_requerida', 'frecuencia_reposicion_meses',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_requerida' => 'integer',
            'frecuencia_reposicion_meses' => 'integer',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    public function equipoProteccion(): BelongsTo
    {
        return $this->belongsTo(EquipoProteccion::class);
    }
}
