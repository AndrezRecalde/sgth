<?php

namespace App\Models\Expediente;

use App\Enums\EstadoSubrogacion;
use App\Enums\MotivoSubrogacion;
use App\Enums\TipoSubrogacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(\App\Observers\SubrogacionObserver::class)]
class Subrogacion extends Model
{
    protected $table = 'subrogaciones';

    // Sin SoftDeletes — registro histórico inmutable

    protected $fillable = [
        'tipo', 'servidor_subrogante_id', 'servidor_subrogado_id',
        'unidad_administrativa_id', 'puesto_subrogado_id',
        'fecha_inicio', 'fecha_fin', 'motivo',
        'resolucion_numero',
        'estado', 'observacion', 'registrado_por', 'movimiento_personal_id',
    ];

    protected function casts(): array
    {
        return [
            'tipo'         => TipoSubrogacion::class,
            'motivo'       => MotivoSubrogacion::class,
            'estado'       => EstadoSubrogacion::class,
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    public function subrogante(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_subrogante_id');
    }

    /**
     * La Acción de Personal que respalda esta subrogación. Mientras no esté
     * registrada, la subrogación permanece pendiente y no surte efecto.
     */
    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_personal_id');
    }

    public function subrogado(): BelongsTo
    {
        // nullable — en encargos no hay titular
        return $this->belongsTo(Servidor::class, 'servidor_subrogado_id');
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function puestoSubrogado(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_subrogado_id');
    }

    /**
     * Quién la registró en el sistema.
     *
     * Se llama `registradoPorUsuario` y no `registradoPor` porque Eloquent
     * serializa las relaciones en snake_case y las mezcla ENCIMA de los
     * atributos: `registradoPor` habría pisado la columna `registrado_por` con
     * el objeto del usuario, y el id habría desaparecido del JSON. Es el mismo
     * nombre con el que MovimientoPersonalResource publica su `autorizadoPor`.
     */
    public function registradoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Surte efecto ese día: activa y dentro de su plazo.
     *
     * Es el único sitio donde se escribe este predicado. Estaba copiado en
     * cuatro —este scope, `SubrogacionService::listarActivas()`,
     * `UnidadAdministrativa::subrogacionesVigentes()` y
     * `FirmanteOrganigramaService::subroganteDe()`— con tres redacciones
     * distintas, y el scope, que era la que estaba en su sitio, no lo usaba
     * nadie: su único llamador era un método que tampoco llamaba nadie.
     *
     * Acepta cadena o Carbon porque quien pregunta por una fecha pasada
     * —reconstruir quién firmaba entonces— la trae como 'Y-m-d', y quien
     * pregunta por hoy trae un Carbon.
     */
    public function scopeActivaEnFecha(Builder $query, Carbon|string $fecha): Builder
    {
        $dia = $fecha instanceof Carbon ? $fecha->toDateString() : $fecha;

        return $query->where('estado', EstadoSubrogacion::ACTIVA)
                     ->whereDate('fecha_inicio', '<=', $dia)
                     ->whereDate('fecha_fin', '>=', $dia);
    }
}
