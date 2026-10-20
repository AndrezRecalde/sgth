<?php

namespace App\Models\Nomina;

use App\Enums\ConceptoNovedadNomina;
use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una novedad tal como se entregó a Financiero (fase 7.3a). Mientras el mes
 * está abierto las novedades no se guardan: se derivan de los actos
 * (`NovedadesNominaService`). Esta fila es la copia de lo entregado, y no se
 * reescribe.
 *
 * @property int $id
 * @property int $entrega_id
 * @property string $mes_afectado
 * @property bool $es_ajuste
 * @property bool $vigente
 * @property string $clave
 * @property ConceptoNovedadNomina $concepto
 * @property int $servidor_id
 * @property ?int $contrato_id
 * @property ?int $movimiento_id
 * @property ?int $subrogacion_id
 * @property ?string $acto
 * @property ?\Illuminate\Support\Carbon $desde
 * @property ?\Illuminate\Support\Carbon $hasta
 * @property ?int $dias
 * @property string $detalle
 */
class NovedadNomina extends Model
{
    protected $table = 'novedades_nomina';

    protected $fillable = [
        'entrega_id', 'mes_afectado', 'es_ajuste', 'vigente', 'clave', 'concepto', 'servidor_id', 'contrato_id',
        'movimiento_id', 'subrogacion_id', 'acto', 'desde', 'hasta', 'dias', 'detalle',
    ];

    protected function casts(): array
    {
        return [
            'es_ajuste' => 'boolean',
            'vigente'   => 'boolean',
            'concepto'  => ConceptoNovedadNomina::class,
            'desde'     => 'date',
            'hasta'     => 'date',
            'dias'      => 'integer',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(EntregaNovedadesNomina::class, 'entrega_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
