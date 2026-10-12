<?php

namespace App\Models\Expediente;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * El contrato colectivo de los obreros (diseño de Acciones de Personal, 9.15;
 * fase 4.6): el documento y las cláusulas que cambian cálculos, dentro de los
 * límites de la ley (CT 224, Mandato 2).
 *
 * Sin el dictamen del Ministerio de Finanzas se tiene por inexistente (Acuerdo
 * MDT-2024-080, reformado por el MDT-2025-056): se guarda, pero sus cláusulas
 * no se aplican.
 */
class ContratoColectivo extends Model
{
    use LogsActivity;

    protected $table = 'contratos_colectivos';

    protected $fillable = [
        'numero',
        'fecha_suscripcion',
        'fecha_registro_mdt',
        'vigencia_desde',
        'vigencia_hasta',
        'dictamen_finanzas_numero',
        'dictamen_finanzas_fecha',
        'dias_adicionales_vacaciones',
        'bonificacion_jubilacion_sbu',
        'bonificacion_retiro_sbu',
        'incremento_pactado_porcentaje',
        'documento_ruta',
        'documento_nombre',
        'observacion',
        'created_by',
        'updated_by',
    ];

    /** La ruta del archivo no sale del API: se descarga por su endpoint. */
    protected $hidden = ['documento_ruta'];

    protected $appends = ['tiene_documento', 'tiene_dictamen'];

    protected function casts(): array
    {
        return [
            'fecha_suscripcion'             => 'date',
            'fecha_registro_mdt'            => 'date',
            'vigencia_desde'                => 'date',
            'vigencia_hasta'                => 'date',
            'dictamen_finanzas_fecha'       => 'date',
            'dias_adicionales_vacaciones'   => 'integer',
            'bonificacion_jubilacion_sbu'   => 'decimal:2',
            'bonificacion_retiro_sbu'       => 'decimal:2',
            'incremento_pactado_porcentaje' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'numero', 'vigencia_desde', 'vigencia_hasta', 'dictamen_finanzas_numero',
                'dias_adicionales_vacaciones', 'bonificacion_jubilacion_sbu', 'bonificacion_retiro_sbu',
                'incremento_pactado_porcentaje', 'documento_nombre',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** Los que rigen en esa fecha, con o sin dictamen. */
    public function scopeVigentesEn(Builder $query, CarbonInterface|string $fecha): Builder
    {
        $dia = $fecha instanceof CarbonInterface ? $fecha->toDateString() : $fecha;

        return $query->whereDate('vigencia_desde', '<=', $dia)
            ->where(fn (Builder $q) => $q->whereNull('vigencia_hasta')->orWhereDate('vigencia_hasta', '>=', $dia));
    }

    public function getTieneDocumentoAttribute(): bool
    {
        return filled($this->documento_ruta);
    }

    /** Sin dictamen del Ministerio de Finanzas, sus cláusulas no se aplican. */
    public function getTieneDictamenAttribute(): bool
    {
        return filled($this->dictamen_finanzas_numero);
    }

    /** Las cláusulas que amplían o añaden licencias (fase 5.3). */
    public function clausulasDeLicencia(): HasMany
    {
        return $this->hasMany(ClausulaDeLicencia::class)->orderBy('id');
    }
}
