<?php

namespace App\Models\Estructura;

use App\Enums\NivelCompetencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una competencia que el puesto exige, y en qué nivel. */
class PuestoCompetencia extends Model
{
    protected $table = 'puesto_competencias';

    protected $fillable = ['puesto_id', 'competencia_id', 'nivel'];

    protected function casts(): array
    {
        return [
            'nivel' => NivelCompetencia::class,
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }
}
