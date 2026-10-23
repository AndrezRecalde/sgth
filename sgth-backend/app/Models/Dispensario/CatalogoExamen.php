<?php

namespace App\Models\Dispensario;

use App\Enums\AreaExamen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un examen que el Dispensario puede pedir.
 *
 * El catálogo es cerrado (D4): no se pide nada que no esté aquí. Por eso no
 * se borra: un examen retirado queda inactivo y las órdenes que ya lo pidieron
 * lo siguen nombrando.
 */
class CatalogoExamen extends Model
{
    protected $table = 'catalogo_examenes';

    protected $fillable = [
        'nombre', 'area', 'orden', 'etiqueta_nota',
        'indicaciones', 'sensible', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'area'     => AreaExamen::class,
            'orden'    => 'integer',
            'sensible' => 'boolean',
            'activo'   => 'boolean',
        ];
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** En el orden de la hoja: por área y, dentro de cada una, por posición. */
    public function scopeEnOrden(Builder $query): Builder
    {
        $casos = collect(AreaExamen::cases())
            ->map(fn (AreaExamen $a) => "WHEN '{$a->value}' THEN {$a->orden()}")
            ->implode(' ');

        return $query
            ->orderByRaw("CASE area {$casos} END")
            ->orderBy('orden')
            ->orderBy('id');
    }
}
