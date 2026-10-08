<?php

namespace App\Models\Dispensario;

use App\Models\User;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class CertificadoMedico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'certificados_medicos';

    /** Cómo llegó a Sirha7 un certificado aprobado. */
    public const REGISTRO_SGTH   = 'sgth';
    public const REGISTRO_MANUAL = 'manual';

    protected $fillable = [
        'consulta_medica_id',
        'servidor_id',
        'emitido_por',
        'dias_reposo',
        'fecha_inicio',
        'fecha_fin',
        'diagnostico_cie10_id',
        'observaciones',
        'permiso_servidor_id',
        'folio',
        'tipo_paciente',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
        'aprobado_por',
        'aprobado_en',
        'registro_sirha7',
        'nota_aprobacion',
        'sirha7_leave_id',
        'sirha7_leave_nombre',
        'sirha7_userid',
        'sirha7_referencia',
        'sirha7_dias_omitidos',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
            'dias_reposo'  => 'integer',
            'anulado_en'   => 'datetime',
            'aprobado_en'  => 'datetime',
            'sirha7_leave_id'      => 'integer',
            'sirha7_userid'        => 'integer',
            'sirha7_dias_omitidos' => 'array',
        ];
    }

    /**
     * ¿Le falta la aprobación de Talento Humano o Trabajo Social?
     *
     * Solo los del servidor titular: los de familiares no justifican ninguna
     * ausencia. Y ni anulados ni ya aprobados.
     */
    public function estaPendienteDeAprobacion(): bool
    {
        return $this->servidor_id !== null
            && $this->anulado_en === null
            && $this->aprobado_en === null;
    }

    /**
     * ¿Lo puede escribir el SGTH en Sirha7, o solo cabe aprobarlo a mano?
     *
     * Como los permisos: solo lo emitido desde la fecha de corte
     * (`services.biometrico.aprobacion_permisos_desde`). Lo anterior ya lo
     * cargó TH a mano, y sin fecha de corte la escritura está apagada.
     */
    public function seRegistraDesdeElSgth(): bool
    {
        $corte = config('services.biometrico.aprobacion_permisos_desde');

        return $corte
            && $this->created_at !== null
            && $this->created_at->gte(Carbon::parse($corte)->startOfDay());
    }

    /**
     * Lo que ven del certificado quienes lo aprueban: folio, fechas, días y
     * médico. Ni diagnóstico ni observaciones, que son datos de salud
     * (decisión del 2026-10-08).
     *
     * @return array{folio: ?string, fecha_inicio: ?string, fecha_fin: ?string, dias_reposo: ?int, medico: ?string}
     */
    public function resumenSinDatosClinicos(): array
    {
        $this->loadMissing('emisor.servidor');
        $medico = $this->emisor?->servidor;

        return [
            'folio'        => $this->folio,
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_fin'    => $this->fecha_fin?->toDateString(),
            'dias_reposo'  => $this->dias_reposo,
            'medico'       => $medico
                ? trim("{$medico->apellido} {$medico->nombre}")
                : $this->emisor?->usuario_ti,
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /** Las filas que se escribieron en dbo.USER_SPEDAY al aprobarlo, una por día. */
    public function filasSirha7(): HasMany
    {
        return $this->hasMany(CertificadoSirha7Fila::class, 'certificado_medico_id');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
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
