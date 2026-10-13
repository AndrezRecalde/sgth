<?php

namespace App\Models\InventarioTi;

use App\Enums\TipoMantenimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lo que se le hizo a un equipo: un preventivo, una reparación o una
 * repotenciación, hecha por TIC o por el proveedor de la vigencia.
 *
 * Los que vienen del Excel de TIC no tienen fecha (no consta) y llevan el
 * número de soporte del sistema antiguo.
 */
class MantenimientoBien extends Model
{
    use HasFactory, SoftDeletes;

    public const POR_TIC = 'tic';

    public const POR_PROVEEDOR = 'proveedor';

    protected $table = 'mantenimientos_bien';

    protected $fillable = [
        'bien_informatico_id', 'ticket_id', 'tipo_mantenimiento', 'realizado_por', 'contrato_vigencia_id',
        'fecha_mantenimiento', 'tecnico_id', 'descripcion', 'costo',
        // El número de soporte del sistema antiguo (D1), como en la custodia.
        'soporte_anterior',
        'anulado_en', 'anulado_por', 'motivo_anulacion',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tipo_mantenimiento'  => TipoMantenimiento::class,
            'fecha_mantenimiento' => 'date',
            'costo'               => 'decimal:2',
            'anulado_en'          => 'datetime',
        ];
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function contratoVigencia(): BelongsTo
    {
        return $this->belongsTo(ContratoVigencia::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_en');
    }
}
