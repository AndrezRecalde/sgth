<?php

namespace App\Models\Estructura;

use App\Enums\NivelCompetencia;
use App\Enums\TipoCompetencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una competencia del diccionario del Ministerio, con su comportamiento
 * observable en cada nivel. La carga la migración del perfil del puesto.
 */
class Competencia extends Model
{
    protected $table = 'competencias';

    protected $fillable = [
        'tipo', 'numero', 'nombre', 'definicion',
        'comportamiento_alto', 'comportamiento_medio', 'comportamiento_bajo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo'   => TipoCompetencia::class,
            'numero' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function puestos(): HasMany
    {
        return $this->hasMany(PuestoCompetencia::class);
    }

    public function comportamiento(NivelCompetencia $nivel): string
    {
        return $this->{"comportamiento_{$nivel->value}"};
    }

    /** El número con que el FOR-02 nombra el comportamiento de este nivel. */
    public function numeroDeComportamiento(NivelCompetencia $nivel): int
    {
        return ($this->numero - 1) * 3 + $nivel->posicion();
    }
}
