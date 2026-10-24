<?php

namespace App\Models\Dispensario;

use App\Enums\EstadoRevisionResultado;
use App\Enums\InterpretacionResultado;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultadoMedico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'resultados_medicos';

    protected $fillable = [
        'historia_clinica_id',
        'consulta_medica_id',
        'orden_examen_id',
        'subido_por',
        'tipo',
        'descripcion',
        'archivo',
        'fecha_resultado',
        'estado_revision',
        'interpretacion',
        'comentario_medico',
        'revisado_por',
        'revisado_en',
        'critico_visto_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_resultado'   => 'date',
            'estado_revision'   => EstadoRevisionResultado::class,
            'interpretacion'    => InterpretacionResultado::class,
            // Como las notas de la consulta: es lectura clínica del paciente.
            'comentario_medico' => 'encrypted',
            'revisado_en'       => 'datetime',
            'critico_visto_en'  => 'datetime',
        ];
    }

    /**
     * Quién responde por este resultado: quien pidió la orden o, si vino
     * suelto, quien atendió la consulta. Es a quien se le queda fijo un
     * crítico en «Mi jornada» (D3). Necesita cargadas la orden y la consulta.
     */
    public function responsableId(): ?int
    {
        return $this->ordenExamen?->solicitado_por ?? $this->consultaMedica?->medico_id;
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /**
     * Los que responden a un médico, como `responsableId()` pero en la
     * consulta: los de las órdenes que pidió y, sin orden, los de sus
     * consultas.
     */
    public function scopeDeResponsable(Builder $query, int $usuarioId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('ordenExamen', fn (Builder $o) => $o->where('solicitado_por', $usuarioId))
            ->orWhere(fn (Builder $s) => $s
                ->whereNull('orden_examen_id')
                ->whereHas('consultaMedica', fn (Builder $c) => $c->where('medico_id', $usuarioId))));
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    /** La orden de la que vino, si vino de una. */
    public function ordenExamen(): BelongsTo
    {
        return $this->belongsTo(OrdenExamen::class);
    }

    /** El envío del Portal del que nació, si el paciente lo mandó (fase 6). */
    public function envioPortal(): HasOne
    {
        return $this->hasOne(EnvioResultado::class);
    }

    /** Los exámenes de la orden que cubre este archivo. */
    public function itemsOrden(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemOrdenExamen::class, 'resultado_orden_items', 'resultado_medico_id', 'orden_examen_item_id'
        )->orderBy('orden_examen_items.id');
    }
}
