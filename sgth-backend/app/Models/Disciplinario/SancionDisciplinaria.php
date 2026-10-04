<?php

namespace App\Models\Disciplinario;

use App\Enums\TipoFalta;
use App\Enums\TipoSancion;
use App\Models\Expediente\MovimientoPersonal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SancionDisciplinaria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sanciones_disciplinarias';

    protected $fillable = [
        'sumario_id',
        'movimiento_personal_id',
        'tipo_falta',
        'tipo_sancion',
        'porcentaje_multa',
        'dias_suspension',
        'fecha_efectiva',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tipo_falta'       => TipoFalta::class,
            'tipo_sancion'     => TipoSancion::class,
            'porcentaje_multa' => 'decimal:2',
            'dias_suspension'  => 'integer',
            'fecha_efectiva'   => 'date',
        ];
    }

    public function sumario(): BelongsTo
    {
        return $this->belongsTo(Sumario::class, 'sumario_id');
    }

    /**
     * La acción de personal que nació de la sanción: la de «Sanción
     * disciplinaria» para una multa o una suspensión, o la cesación de
     * funciones de una destitución.
     */
    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_personal_id');
    }

    /**
     * El descuento referencial en el JSON, para el detalle del sumario. Solo
     * cuando la acción viene cargada: sin ella no hay remuneración congelada,
     * y cargarla aquí sería una consulta por fila.
     */
    protected $appends = ['descuento_referencial'];

    public function getDescuentoReferencialAttribute(): ?array
    {
        if (! $this->relationLoaded('movimientoPersonal') || ! $this->movimientoPersonal) {
            return null;
        }

        $remuneracion = $this->movimientoPersonal->remuneracion_origen;

        return $this->descuentoReferencial($remuneracion !== null ? (float) $remuneracion : null);
    }

    /** Las sanciones que tocan la remuneración y van a Financiero. */
    public function afectaRemuneracion(): bool
    {
        return in_array($this->tipo_sancion, [TipoSancion::MULTA, TipoSancion::SUSPENSION], true);
    }

    /**
     * Lo que se descuenta, como referencia para Financiero, que es quien lo
     * aplica en el rol de pagos con su propio sistema (decisión de TH,
     * 2026-10-04):
     *
     * - Multa: el porcentaje sobre la remuneración mensual unificada.
     * - Suspensión sin goce de remuneración: la remuneración del mes entre 30
     *   por los días, contados como días calendario desde que surte efecto.
     *
     * La remuneración es la que la acción de personal congeló al crearse
     * (`remuneracion_origen`), no la de hoy: un documento reimpreso tiene que
     * seguir diciendo la misma cifra.
     *
     * @return array{sancion: string, detalle: string, desde: string, hasta: ?string,
     *               base: ?float, monto: ?float}|null
     */
    public function descuentoReferencial(?float $remuneracion): ?array
    {
        if (! $this->afectaRemuneracion()) {
            return null;
        }

        $desde = $this->fecha_efectiva;
        $base  = $remuneracion !== null && $remuneracion > 0 ? $remuneracion : null;

        if ($this->tipo_sancion === TipoSancion::MULTA) {
            $porcentaje = (float) $this->porcentaje_multa;

            return [
                'sancion' => $this->tipo_sancion->etiqueta(),
                'detalle' => rtrim(rtrim(number_format($porcentaje, 2, ',', '.'), '0'), ',')
                    .' % de la remuneración mensual unificada',
                'desde'   => $desde?->toDateString(),
                'hasta'   => null,
                'base'    => $base,
                'monto'   => $base !== null ? round($base * $porcentaje / 100, 2) : null,
            ];
        }

        $dias = (int) $this->dias_suspension;

        return [
            'sancion' => 'Suspensión temporal sin goce de remuneración',
            'detalle' => $dias === 1 ? '1 día' : "{$dias} días",
            'desde'   => $desde?->toDateString(),
            'hasta'   => $desde?->copy()->addDays(max($dias - 1, 0))->toDateString(),
            'base'    => $base,
            'monto'   => $base !== null ? round($base / 30 * $dias, 2) : null,
        ];
    }
}
