<?php

namespace App\Models\Estructura;

use App\Enums\NivelInstruccion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una alternativa de instrucción y experiencia del perfil: el manual suele dar
 * tres («técnico superior y 6 años, o tecnológico superior y 5, o tercer nivel
 * y 2 años 6 meses»).
 */
class PuestoRequisitoInstruccion extends Model
{
    protected $table = 'puesto_requisitos_instruccion';

    protected $fillable = ['puesto_perfil_id', 'nivel_instruccion', 'detalle', 'meses_experiencia', 'orden'];

    protected function casts(): array
    {
        return [
            'nivel_instruccion' => NivelInstruccion::class,
            'meses_experiencia' => 'integer',
            'orden'             => 'integer',
        ];
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(PuestoPerfil::class, 'puesto_perfil_id');
    }
}
