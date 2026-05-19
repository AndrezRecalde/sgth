<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiagnosticoCie10 extends Model
{
    use HasFactory;

    protected $table = 'diagnosticos_cie10';

    protected $fillable = [
        'codigo',
        'descripcion',
        'categoria',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        return $query->where('codigo', 'ilike', "%{$termino}%")
                     ->orWhere('descripcion', 'ilike', "%{$termino}%");
    }
}
