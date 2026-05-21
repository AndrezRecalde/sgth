<?php

namespace App\Models\Expediente;

use App\Enums\TipoParentesco;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CargaFamiliar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cargas_familiares';

    protected $fillable = [
        'servidor_id',
        'apellidos',
        'nombres',
        'parentesco',
        'fecha_nacimiento',
        'persona_con_discapacidad',
        'posee_enfermedad_catastrofica',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'parentesco'                   => TipoParentesco::class,
            'fecha_nacimiento'             => 'date',
            'persona_con_discapacidad'     => 'boolean',
            'posee_enfermedad_catastrofica' => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function discapacidades(): HasMany
    {
        return $this->hasMany(DiscapacidadCargaFamiliar::class);
    }

    public function enfermedadesCatastroficas(): HasMany
    {
        return $this->hasMany(EnfermedadCatastroficaCargaFamiliar::class);
    }
}