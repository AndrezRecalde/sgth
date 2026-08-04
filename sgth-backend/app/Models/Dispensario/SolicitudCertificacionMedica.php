<?php

namespace App\Models\Dispensario;

use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudCertificacionMedica extends Model
{
    protected $table = 'solicitudes_certificacion_medica';

    protected $fillable = [
        'tipo_evento', 'origen',
        'servidor_id', 'postulante_id', 'convocatoria_id',
        'movimiento_personal_id',
        'cedula_paciente', 'nombres_paciente',
        'correo_paciente', 'puesto_solicitado',
        'solicitado_por', 'estado',
        'fecha_limite', 'ficha_femo_id',
        'dictamen', 'observacion_medica',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class);
    }

    public function convocatoria(): BelongsTo
    {
        return $this->belongsTo(Convocatoria::class);
    }

    /**
     * Acción de personal que exige este dictamen. En reclutamiento se enlaza
     * al confirmar la incorporación; en el resto de acciones, al suscribirse.
     */
    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_personal_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function fichaSaludOcupacional(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_femo_id');
    }

    public function constantesVitales(): HasOne
    {
        return $this->hasOne(SolicitudConstantesVitales::class, 'solicitud_id');
    }
}
