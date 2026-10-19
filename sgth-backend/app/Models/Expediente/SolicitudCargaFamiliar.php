<?php

namespace App\Models\Expediente;

use App\Enums\EstadoSolicitudCarga;
use App\Enums\TipoParentesco;
use App\Enums\TipoSolicitudCarga;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una solicitud del servidor desde «Mi perfil» sobre sus cargas familiares:
 * registrar a un familiar, corregir sus datos o darlo de baja
 * (docs/diseno-cargas-familiares-portal.md). No cuenta hasta que Talento
 * Humano la valida; entonces se aplica sobre `cargas_familiares`.
 */
class SolicitudCargaFamiliar extends Model
{
    use LogsActivity;

    protected $table = 'solicitudes_carga_familiar';

    protected $fillable = [
        'servidor_id',
        'tipo',
        'carga_familiar_id',
        'cedula',
        'nombres',
        'apellidos',
        'parentesco',
        'fecha_nacimiento',
        'genero',
        'motivo',
        'documento_ruta',
        'documento_nombre',
        'estado',
        'registrado_por',
        'resuelto_por',
        'resuelto_en',
        'motivo_rechazo',
        'retirada_en',
    ];

    /** La ruta del archivo no sale del API: se descarga por su endpoint. */
    protected $hidden = ['documento_ruta'];

    protected function casts(): array
    {
        return [
            'tipo'             => TipoSolicitudCarga::class,
            'estado'           => EstadoSolicitudCarga::class,
            'parentesco'       => TipoParentesco::class,
            'fecha_nacimiento' => 'date',
            'resuelto_en'      => 'datetime',
            'retirada_en'      => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['estado', 'carga_familiar_id', 'resuelto_por', 'motivo_rechazo', 'retirada_en'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    /** Con las borradas: una baja o una corrección siguen diciendo sobre quién eran. */
    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class)->withTrashed();
    }

    public function resueltoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }
}
