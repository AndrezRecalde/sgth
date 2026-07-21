<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\FaseProgramaDrogas;

class ProgramaDrogaActividad extends Model
{
    protected $table = 'programa_drogas_actividades';

    protected $fillable = [
        'fase', 'nombre', 'descripcion', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'fase' => FaseProgramaDrogas::class,
            'activo' => 'boolean',
        ];
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(ProgramaDrogaSeguimiento::class, 'programa_droga_actividad_id');
    }
}
