<?php

namespace App\Models\Estructura;

use App\Enums\NivelDelPuesto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La descripción del puesto, como la ficha del Manual de Puestos
 * (docs/diseno-manual-de-puestos.md, P1): misión, relaciones, requisitos y
 * capacitación. Las actividades y las competencias son del puesto
 * (`PuestoActividad`, `PuestoCompetencia`).
 *
 * En tabla aparte y no en `puestos`: el perfil es largo y lo leen pocas
 * pantallas, y el puesto ya lo cargan las acciones de personal y el FEMO. Sin
 * estados ni versiones: la aprobación queda como hoy (TH, D6).
 */
class PuestoPerfil extends Model
{
    protected $table = 'puesto_perfiles';

    /** Los campos de texto del perfil, en el orden de la ficha. */
    public const CAMPOS = [
        'mision', 'relaciones', 'nivel', 'area_conocimiento', 'especificidad_experiencia', 'capacitacion', 'subproceso',
    ];

    protected $fillable = ['puesto_id', ...self::CAMPOS];

    protected function casts(): array
    {
        return ['nivel' => NivelDelPuesto::class];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    /** Las alternativas de instrucción y experiencia, en el orden de la ficha. */
    public function requisitos(): HasMany
    {
        return $this->hasMany(PuestoRequisitoInstruccion::class)->orderBy('orden');
    }
}
