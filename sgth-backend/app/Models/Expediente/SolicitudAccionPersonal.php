<?php

namespace App\Models\Expediente;

use App\Enums\EstadoSolicitudAccion;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoSolicitudAccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una solicitud del servidor desde el portal: renuncia, licencia sin
 * remuneración o jubilación (diseño, 9.13; fase 6.3b). Se atiende preparando
 * su acción de personal, o se rechaza con el motivo.
 */
class SolicitudAccionPersonal extends Model
{
    use LogsActivity;

    /** La renuncia sin respuesta se tiene por aceptada a los 15 días (Reglamento a la LOSEP, Art. 102). */
    public const DIAS_DE_LA_RENUNCIA = 15;

    protected $table = 'solicitudes_accion_personal';

    protected $fillable = [
        'folio',
        'servidor_id',
        'tipo',
        'causal',
        'fecha_presentacion',
        'fecha_desde',
        'fecha_hasta',
        'motivo',
        'documento_ruta',
        'documento_nombre',
        'estado',
        'created_by',
    ];

    /** La ruta del archivo no sale del API: se descarga por su endpoint. */
    protected $hidden = ['documento_ruta'];

    protected function casts(): array
    {
        return [
            'tipo'               => TipoSolicitudAccion::class,
            'causal'             => SubtipoMovimientoPersonal::class,
            'estado'             => EstadoSolicitudAccion::class,
            'fecha_presentacion' => 'date',
            'fecha_desde'        => 'date',
            'fecha_hasta'        => 'date',
            'atendida_en'        => 'datetime',
            'retirada_en'        => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['estado', 'movimiento_personal_id', 'motivo_rechazo', 'retirada_en'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * ¿Es una renuncia que, sin respuesta, ya se tiene por aceptada? Pasados
     * los 15 días desde que la presentó (Reglamento a la LOSEP, Art. 102):
     * Talento Humano ya no la rechaza; le toca preparar la acción.
     */
    public function aceptadaPorElPlazo(): bool
    {
        return $this->tipo === TipoSolicitudAccion::RENUNCIA
            && $this->estado === EstadoSolicitudAccion::PENDIENTE
            && $this->fecha_presentacion->copy()->addDays(self::DIAS_DE_LA_RENUNCIA)->lt(now()->startOfDay());
    }

    /** La causal con que se prepara la acción: la que trae o la que eligió. */
    public function causalDeLaAccion(): ?SubtipoMovimientoPersonal
    {
        return $this->tipo->causalFija() ?? $this->causal;
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class);
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendida_por');
    }
}
