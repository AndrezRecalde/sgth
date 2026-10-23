<?php

namespace App\Models\Dispensario;

use App\Enums\EstadoOrdenExamen;
use App\Enums\PrioridadOrdenExamen;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una orden de exámenes: lo que el médico pidió y el paciente lleva al
 * laboratorio, con su folio impreso.
 */
class OrdenExamen extends Model
{
    /** Días que vale una orden antes de darse por vencida (D5). */
    public const DIAS_VIGENCIA = 90;

    protected $table = 'ordenes_examen';

    protected $fillable = [
        'folio', 'historia_clinica_id', 'consulta_medica_id', 'ficha_salud_ocupacional_id', 'solicitado_por',
        'fecha_solicitud', 'diagnostico_cie10_id', 'prioridad', 'indicaciones',
        'laboratorio_destino', 'consentimiento_firmado', 'estado',
        'anulado_en', 'anulado_por', 'motivo_anulacion',
    ];

    protected $appends = ['vencida'];

    protected function casts(): array
    {
        return [
            'fecha_solicitud'        => 'date',
            'prioridad'              => PrioridadOrdenExamen::class,
            'estado'                 => EstadoOrdenExamen::class,
            'consentimiento_firmado' => 'boolean',
            'anulado_en'             => 'datetime',
        ];
    }

    /**
     * Abierta y con el plazo pasado. Se calcula al leerla: deja de contar como
     * pendiente y pasa al seguimiento, sin que nada tenga que escribirlo.
     */
    protected function vencida(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado?->abierta()
            && $this->fecha_solicitud?->lt(today()->subDays(self::DIAS_VIGENCIA)));
    }

    /** En el orden en que se pidieron: sin él, Postgres los devuelve como le conviene. */
    public function items(): HasMany
    {
        return $this->hasMany(ItemOrdenExamen::class)->orderBy('id');
    }

    /** Lo que volvió del laboratorio contra esta orden. */
    public function resultados(): HasMany
    {
        return $this->hasMany(ResultadoMedico::class);
    }

    /** Lo que el paciente envió desde el Portal (fase 6), del más reciente al más antiguo. */
    public function envios(): HasMany
    {
        return $this->hasMany(EnvioResultado::class)->orderByDesc('id');
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    /** La ficha ocupacional que la pidió, si no salió de una consulta (fase 5). */
    public function fichaSaludOcupacional(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class);
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function diagnosticoCie10(): BelongsTo
    {
        return $this->belongsTo(DiagnosticoCie10::class, 'diagnostico_cie10_id');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
