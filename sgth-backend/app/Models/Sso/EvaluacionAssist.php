<?php

namespace App\Models\Sso;

use App\Enums\EstadoCampaniaSso;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\Estructura\UnidadAdministrativa;

class EvaluacionAssist extends Model
{
    protected $table = 'evaluaciones_assist';

    protected $appends = ['estado_campania'];

    protected $fillable = [
        'periodo', 'unidad_administrativa_id', 'codigo_acceso',
        'fecha_apertura', 'fecha_cierre', 'activa', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'date',
            'fecha_cierre' => 'date',
            'activa' => 'boolean',
        ];
    }

    /**
     * El estado real de la ventana de la campaña, para quien la lea.
     *
     * Va en `$appends` a propósito: un accesor que no está ahí no viaja al
     * frontend, y la pantalla se quedaría pintando `activa`, que es lo que
     * hacía. `activa` solo dice si alguien la cerró a mano; la ventana la
     * componen además `fecha_apertura` y `fecha_cierre`. El cálculo vive en
     * `EstadoCampaniaSso` porque lo comparten esta columna y la guarda del
     * cuestionario público, y así se prueba sin base de datos.
     */
    protected function estadoCampania(): Attribute
    {
        return Attribute::get(fn (): string => EstadoCampaniaSso::desde(
            (bool) $this->activa,
            $this->fecha_apertura,
            $this->fecha_cierre,
        )->value);
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaAssist::class, 'evaluacion_assist_id');
    }
}
