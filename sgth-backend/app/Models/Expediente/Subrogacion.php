<?php

namespace App\Models\Expediente;

use App\Enums\EstadoSubrogacion;
use App\Enums\MotivoSubrogacion;
use App\Enums\TipoSubrogacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
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
        'resolucion_numero', 'documento_respaldo',
        'estado', 'observacion', 'registrado_por',
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

    // Scope para consultas en tiempo de ejecución (Policies)
    public function scopeActivaEnFecha(Builder $query, Carbon $fecha): Builder
    {
        return $query->where('estado', EstadoSubrogacion::ACTIVA)
                     ->where('fecha_inicio', '<=', $fecha)
                     ->where('fecha_fin', '>=', $fecha);
    }
}
