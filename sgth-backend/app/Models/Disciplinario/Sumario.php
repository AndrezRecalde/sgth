<?php

namespace App\Models\Disciplinario;

use App\Enums\EstadoSumario;
use App\Enums\ProcedimientoDisciplinario;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Sumario extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'sumarios';

    protected $fillable = [
        'servidor_id',
        // El sumario de la LOSEP o el procedimiento del reglamento interno de
        // un obrero, con la falta que se le imputa (fase 4.4).
        'procedimiento',
        'falta_reglamento_id',
        'motivo',
        'estado',
        'fecha_apertura',
        'notificado_sn',
        'fecha_notificacion',
        'fecha_termino_prueba',
        'fecha_informe',
        'fecha_resolucion',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado'               => EstadoSumario::class,
            'procedimiento'        => ProcedimientoDisciplinario::class,
            'fecha_apertura'       => 'date',
            'notificado_sn'        => 'boolean',
            'fecha_notificacion'   => 'date',
            'fecha_termino_prueba' => 'date',
            'fecha_informe'        => 'date',
            'fecha_resolucion'     => 'date',
        ];
    }

    /**
     * Auditoría: el sumario decide la destitución de un servidor LOSEP, que es
     * lo mismo que decide el visto bueno para un obrero. `VistoBueno` se
     * auditaba desde el principio y este no, aunque el acto tiene el mismo
     * peso: se registran el estado, el motivo y las fechas de los hitos, que
     * son las que sostienen los plazos legales.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'estado',
                'motivo',
                'fecha_notificacion',
                'fecha_termino_prueba',
                'fecha_informe',
                'fecha_resolucion',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    /** La falta del reglamento interno que se imputa al obrero (fase 4.4). */
    public function faltaReglamento(): BelongsTo
    {
        return $this->belongsTo(FaltaReglamentoInterno::class, 'falta_reglamento_id');
    }

    public function esDelReglamentoInterno(): bool
    {
        return $this->procedimiento === ProcedimientoDisciplinario::REGLAMENTO_INTERNO;
    }

    /**
     * La sanción vigente. La que quedó sin efecto al anularse su acción no
     * cuenta (fase 2.5): el sumario vuelve a resolverse con otra.
     */
    public function sancion(): HasOne
    {
        return $this->hasOne(SancionDisciplinaria::class, 'sumario_id')->whereNull('anulada_en');
    }

    /** Las que quedaron sin efecto, de la más reciente a la más antigua. */
    public function sancionesSinEfecto(): HasMany
    {
        return $this->hasMany(SancionDisciplinaria::class, 'sumario_id')
            ->whereNotNull('anulada_en')
            ->orderByDesc('anulada_en')
            ->orderByDesc('id');
    }
}
