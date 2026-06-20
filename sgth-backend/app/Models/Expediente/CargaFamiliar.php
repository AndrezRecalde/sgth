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
        'cedula',
        'apellidos',
        'nombres',
        'parentesco',
        'fecha_nacimiento',
        'persona_con_discapacidad',
        'posee_enfermedad_catastrofica',
        'observaciones',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'parentesco'                   => TipoParentesco::class,
            'fecha_nacimiento'             => 'date',
            'persona_con_discapacidad'     => 'boolean',
            'posee_enfermedad_catastrofica' => 'boolean',
            'estado'                        => 'boolean',
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

    public function scopeActivos(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('estado', true);
    }

    public function historiaClinica(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(
            \App\Models\Dispensario\HistoriaClinica::class,
            'carga_familiar_id'
        );
    }
}