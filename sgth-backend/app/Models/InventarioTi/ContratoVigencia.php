<?php

namespace App\Models\InventarioTi;

use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El contrato de vigencia tecnológica de un proceso de compra: el proveedor
 * mantiene los equipos durante unos años, con un cronograma, y un servidor
 * administra el contrato. En el Excel de TIC era un texto por equipo
 * («VIGENCIA TECNOLOGICA, PROCESO SIE-GADPE-018-2021; CRONOGRAMA…»).
 */
class ContratoVigencia extends Model
{
    use SoftDeletes;

    protected $table = 'contratos_vigencia';

    protected $fillable = [
        'proceso', 'proveedor', 'objeto', 'administrador_id',
        'fecha_inicio', 'fecha_fin', 'observaciones', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'administrador_id');
    }

    public function cronograma(): HasMany
    {
        return $this->hasMany(CronogramaVigencia::class)->orderBy('fecha_desde')->orderBy('id');
    }

    public function bienes(): HasMany
    {
        return $this->hasMany(BienInformatico::class);
    }
}
