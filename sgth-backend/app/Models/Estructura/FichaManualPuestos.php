<?php

namespace App\Models\Estructura;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una ficha del Manual de Puestos 2018-2019, cargada para revisarla
 * (docs/diseno-manual-de-puestos.md, P4). Es staging: no es el perfil de
 * ningún puesto hasta que Talento Humano la empareja con uno y la carga
 * (PR 12). Entonces guarda en qué puesto, quién y cuándo.
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
        'ficha', 'propuesta', 'avisos', 'puesto_id', 'cargada_por', 'cargada_en',
    ];

    protected function casts(): array
    {
        return [
            'grado'      => 'integer',
            'plazas'     => 'integer',
            'ficha'      => 'array',
            'propuesta'  => 'array',
            'avisos'     => 'array',
            'cargada_en' => 'datetime',
        ];
    }

    /** El puesto en que se cargó; con los borrados, para no perder la pista. */
    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class)->withTrashed();
    }

    public function cargadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargada_por');
    }
}
