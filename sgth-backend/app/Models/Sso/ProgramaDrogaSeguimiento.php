<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\User;
use App\Enums\EstadoActividadPrograma;

class ProgramaDrogaSeguimiento extends Model
{
    protected $table = 'programa_drogas_seguimiento';

    protected $fillable = [
        'programa_droga_actividad_id', 'periodo', 'estado',
        'fecha_ejecucion', 'observaciones', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoActividadPrograma::class,
            'fecha_ejecucion' => 'date',
        ];
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(ProgramaDrogaActividad::class, 'programa_droga_actividad_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoSso::class, 'documentable');
    }
}
