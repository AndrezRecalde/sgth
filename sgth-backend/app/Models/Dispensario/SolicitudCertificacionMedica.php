<?php

namespace App\Models\Dispensario;

use App\Models\Expediente\Servidor;
use App\Models\Seleccion\Postulante;
use App\Models\Seleccion\Convocatoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudCertificacionMedica extends Model
{
    protected $table = 'solicitudes_certificacion_medica';

    protected $fillable = [
        'tipo_evento', 'origen',
        'servidor_id', 'postulante_id', 'convocatoria_id',
        'cedula_paciente', 'nombres_paciente',
        'correo_paciente', 'puesto_solicitado',
        'solicitado_por', 'estado',
        'fecha_limite', 'ficha_femo_id', 'observaciones',
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

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function fichaSaludOcupacional(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_femo_id');
    }
}
