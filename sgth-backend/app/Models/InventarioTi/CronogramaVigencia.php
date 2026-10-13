<?php

namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una ventana de mantenimiento del contrato de vigencia («del 15 al 30 de octubre»). */
class CronogramaVigencia extends Model
{
    protected $table = 'cronogramas_vigencia';

    protected $fillable = ['contrato_vigencia_id', 'fecha_desde', 'fecha_hasta', 'descripcion'];

    protected function casts(): array
    {
        return ['fecha_desde' => 'date', 'fecha_hasta' => 'date'];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoVigencia::class, 'contrato_vigencia_id');
    }
}
