<?php

namespace App\Models\Dispensario;

use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'beneficiarios';

    protected $fillable = [
        'servidor_id',
        'nombre',
        'apellido',
        'fecha_nacimiento',
        'genero',
        'cedula',
        'tipo_familiar',
        'estado'
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'estado' => 'boolean',
    ];

    /**
     * Scope para listar solo beneficiarios activos
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    /**
     * El servidor público al que pertenece este beneficiario (titular).
     */
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    /**
     * La historia clínica de este beneficiario en el dispensario médico.
     */
    public function historiaClinica(): HasOne
    {
        return $this->hasOne(HistoriaClinica::class, 'beneficiario_id');
    }
}
