<?php

namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Model;

/**
 * Una ficha del Manual de Puestos 2018-2019, cargada para revisarla
 * (docs/diseno-manual-de-puestos.md, P4). Es staging: no es el perfil de
 * ningún puesto hasta que Talento Humano la empareje y la confirme (PR 12).
 *
 * @property array<string, mixed> $ficha      Lo que trajo el extractor, tal cual.
 * @property array<string, mixed> $propuesta  El perfil en los términos del SGTH.
 * @property list<string>         $avisos     Lo que TH tiene que mirar.
 */
class FichaManualPuestos extends Model
{
    protected $table = 'fichas_manual_puestos';

    protected $fillable = [
        'archivo', 'proceso', 'carpeta', 'denominacion', 'unidad', 'grupo_ocupacional', 'grado', 'plazas',
        'ficha', 'propuesta', 'avisos',
    ];

    protected function casts(): array
    {
        return [
            'grado'     => 'integer',
            'plazas'    => 'integer',
            'ficha'     => 'array',
            'propuesta' => 'array',
            'avisos'    => 'array',
        ];
    }
}
