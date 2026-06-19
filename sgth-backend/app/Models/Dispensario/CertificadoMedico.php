<?php

namespace App\Models\Dispensario;

use App\Models\User;
use App\Models\Asistencia\PermisoServidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificadoMedico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'certificados_medicos';

    protected $fillable = [
        'consulta_medica_id',
        'emitido_por',
        'dias_reposo',
        'fecha_inicio',
        'fecha_fin',
        'diagnostico_cie10_id',
        'observaciones',
        'permiso_servidor_id',
        'folio',
        'tipo_paciente',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
            'dias_reposo'  => 'integer',
        ];
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function diagnosticoCie10(): BelongsTo
    {
        return $this->belongsTo(DiagnosticoCie10::class);
    }

    public function permisoServidor(): BelongsTo
    {
        return $this->belongsTo(PermisoServidor::class);
    }
}
