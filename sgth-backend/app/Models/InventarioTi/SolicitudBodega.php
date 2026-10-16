<?php

namespace App\Models\InventarioTi;

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La «Solicitud de materiales informáticos a bodega» (fase 5.4c): lo que TIC le
 * pide a Gestión Administrativa para un servidor y, si hace falta, un equipo.
 * Sale de un ticket «Consumibles» o, mientras no arranque la mesa de ayuda, del
 * módulo de consumibles. Cuando Bodega despacha, TIC anota lo despachado: esa
 * es la entrega.
 */
class SolicitudBodega extends Model
{
    public const PENDIENTE = 'pendiente';

    public const DESPACHADA = 'despachada';

    public const ANULADA = 'anulada';

    protected $table = 'solicitudes_bodega';

    protected $fillable = [
        'anio', 'numero', 'fecha', 'ticket_id', 'servidor_id', 'unidad_administrativa_id', 'bien_informatico_id',
        'motivo', 'estado', 'firmantes', 'fecha_despacho', 'despachada_por',
        'anulada_en', 'anulada_por', 'motivo_anulacion', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'anio'           => 'integer',
            'numero'         => 'integer',
            'fecha'          => 'date',
            'firmantes'      => 'array',
            'fecha_despacho' => 'date',
            'anulada_en'     => 'datetime',
        ];
    }

    /** SB-2026-0001. */
    public function codigo(): string
    {
        return sprintf('SB-%d-%04d', $this->anio, $this->numero);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SolicitudBodegaItem::class, 'solicitud_id')->orderBy('id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoConsumible::class, 'solicitud_bodega_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_administrativa_id');
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id')->withTrashed();
    }

    public function creadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function despachadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'despachada_por');
    }

    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_por');
    }
}
