<?php

namespace App\Models\InventarioTi;

use App\Enums\DocumentoIngresoConsumible;
use App\Enums\MotivoAjusteConsumible;
use App\Enums\TipoMovimientoConsumible;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento del kárdex de un consumible (fase 5.4a): apertura, ingreso,
 * entrega o ajuste. La cantidad va con signo; `afecta_saldo` es falso en la
 * entrega de lo que despacha Bodega. No se borra: se anula con su motivo y
 * sigue a la vista.
 */
class MovimientoConsumible extends Model
{
    protected $table = 'movimientos_consumible';

    protected $fillable = [
        'consumible_id', 'tipo', 'cantidad', 'afecta_saldo', 'fecha',
        'bien_informatico_id', 'servidor_retira_id', 'unidad_administrativa_id', 'ticket_id', 'solicitud_bodega_id',
        'tipo_documento', 'numero_documento', 'fecha_compra', 'motivo_ajuste', 'observacion',
        'registrado_por', 'anulado_en', 'anulado_por', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'tipo'           => TipoMovimientoConsumible::class,
            'cantidad'       => 'integer',
            'afecta_saldo'   => 'boolean',
            'fecha'          => 'date',
            'tipo_documento' => DocumentoIngresoConsumible::class,
            'fecha_compra'   => 'date',
            'motivo_ajuste'  => MotivoAjusteConsumible::class,
            'anulado_en'     => 'datetime',
        ];
    }

    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id')->withTrashed();
    }

    public function retira(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_retira_id');
    }

    /** La unidad del momento de la entrega: el consumo cuenta para ella aunque el servidor cambie después. */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function solicitudBodega(): BelongsTo
    {
        return $this->belongsTo(SolicitudBodega::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /** Los que cuentan: sin anular. */
    public function scopeVivos(Builder $q): Builder
    {
        return $q->whereNull('anulado_en');
    }

    /**
     * El orden del kárdex: por fecha y, en el mismo día, primero lo que entra
     * y después lo que sale; a igualdad, en el orden en que se registró.
     */
    public function scopeEnOrdenDeKardex(Builder $q): Builder
    {
        return $q->orderBy('fecha')->orderByRaw('cantidad < 0')->orderBy('id');
    }
}
