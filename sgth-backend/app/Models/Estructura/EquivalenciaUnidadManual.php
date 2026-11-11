<?php

namespace App\Models\Estructura;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A qué unidad de hoy corresponde una gestión del Manual de Puestos 2018-2019
 * (docs/diseno-manual-de-puestos.md, P4). La elige Talento Humano en la
 * revisión; sin ella, el SGTH sugiere una por el nombre.
 *
 * `gestion` es la clave normalizada (`RevisionDelManualService::clave`).
 */
class EquivalenciaUnidadManual extends Model
{
    protected $table = 'equivalencias_unidades_manual';

    protected $fillable = ['gestion', 'unidad_administrativa_id'];

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }
}
