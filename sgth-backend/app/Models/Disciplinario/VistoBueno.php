<?php

namespace App\Models\Disciplinario;

use App\Enums\CausalVistoBueno;
use App\Enums\EstadoVistoBueno;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VistoBueno extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'vistos_buenos';

    protected $fillable = [
        'servidor_id',
        'causal',
        'estado',
        'numero_tramite_mdt',
        'inspectoria',
        'inspector_nombre',
        'fecha_solicitud',
        'fecha_notificacion',
        'fecha_resolucion',
        'hechos',
        'resolucion_detalle',
        'impugnacion_referencia',
        'fecha_impugnacion',
        'movimiento_personal_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'causal'             => CausalVistoBueno::class,
            'estado'             => EstadoVistoBueno::class,
            'fecha_solicitud'    => 'date',
            'fecha_notificacion' => 'date',
            'fecha_resolucion'   => 'date',
            'fecha_impugnacion'  => 'date',
        ];
    }

    /*
    | La ruta del PDF no sale en el JSON: es un detalle del disco, y el archivo
    | se baja por GET vistos-buenos/{id}/documento, que pasa por la
    | autorización. La pantalla solo necesita saber si hay uno y cómo se llama.
    |
    | `documento_respaldo` y `documento_nombre` tampoco están en $fillable:
    | antes la ruta se aceptaba como texto libre en el alta y en la
    | transición, y una ruta inventada es una descarga de cualquier archivo
    | del disco. Solo los escribe VistoBuenoService::adjuntarResolucion().
    */
    protected $hidden = ['documento_respaldo'];

    protected $appends = ['tiene_documento'];

    public function getTieneDocumentoAttribute(): bool
    {
        return filled($this->documento_respaldo);
    }

    /**
     * Auditoría: el trámite decide la terminación del vínculo de un
     * trabajador, así que se registra el cambio de estado, la resolución del
     * Inspector y el número de trámite del Ministerio.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'estado',
                'causal',
                'numero_tramite_mdt',
                'fecha_resolucion',
                'resolucion_detalle',
                // Quién cambió el PDF de la resolución, y la impugnación.
                'documento_nombre',
                'impugnacion_referencia',
                'fecha_impugnacion',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    /** Cesación de funciones generada al concederse el visto bueno. */
    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_personal_id');
    }
}
