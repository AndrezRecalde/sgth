<?php

namespace App\Models\Expediente;

use App\Enums\TipoMovimientoPersonal;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Sin SoftDeletes por ser inmutable
class MovimientoPersonal extends Model
{
    protected $table = 'movimientos_personal';

    protected $fillable = [
        'servidor_id',
        'tipo_movimiento',
        'codigo',
        'descripcion',
        'fecha_efectiva',
        'fecha_inicio',
        'fecha_fin',
        'unidad_origen_id',
        'unidad_destino_id',
        'puesto_origen_id',
        'puesto_destino_id',
        'resolucion_numero',
        'documento_respaldo',
        'autorizado_por',
        'observacion',
        'lugar_trabajo',
        'caucionado',
        'caucion_numero',
        'caucion_fecha',
    ];

    protected function casts(): array
    {
        return [
            'tipo_movimiento' => TipoMovimientoPersonal::class,
            'fecha_efectiva'  => 'date',
            'fecha_inicio'    => 'date',
            'fecha_fin'       => 'date',
            'caucionado'      => 'boolean',
            'caucion_fecha'   => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function unidadOrigen(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_origen_id');
    }

    public function unidadDestino(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_destino_id');
    }

    public function puestoOrigen(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_origen_id');
    }

    public function puestoDestino(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_destino_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }
}
