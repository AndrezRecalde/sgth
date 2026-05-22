<?php

namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ExtensionTelefonica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'extensiones_telefonicas';

    protected $fillable = [
        'unidad_administrativa_id',
        'numero_extension',
        'responsable',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    /**
     * Unidad administrativa a la que pertenece la extensión.
     */
    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    /**
     * Scope para filtrar solo las extensiones activas.
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', true);
    }
}
