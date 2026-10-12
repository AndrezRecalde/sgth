<?php

namespace App\Models\Expediente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lo que pasa después de que un acto cierra el vínculo y la persona no queda
 * con otro (diseño, 9.9; fase 7.4a): sus pasos, con su responsable. Se abre
 * sola al surtir efecto el cierre, se cierra sola cuando cada paso está hecho
 * o no aplica, y se cancela si el cese se anula.
 *
 * @property int $id
 * @property int $servidor_id
 * @property int $contrato_id
 * @property int $movimiento_cierre_id
 * @property \Illuminate\Support\Carbon $fecha_salida
 * @property string $estado
 * @property ?\Illuminate\Support\Carbon $cerrada_en
 * @property ?\Illuminate\Support\Carbon $cancelada_en
 * @property ?string $motivo_cancelacion
 */
class Desvinculacion extends Model
{
    public const ABIERTA = 'abierta';
    public const CERRADA = 'cerrada';
    public const CANCELADA = 'cancelada';

    protected $table = 'desvinculaciones';

    protected $fillable = [
        'servidor_id', 'contrato_id', 'movimiento_cierre_id', 'fecha_salida', 'estado',
        'cerrada_en', 'cancelada_en', 'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_salida' => 'date',
            'cerrada_en'   => 'datetime',
            'cancelada_en' => 'datetime',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoServidor::class, 'contrato_id');
    }

    public function movimientoCierre(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class, 'movimiento_cierre_id');
    }

    public function pasos(): HasMany
    {
        return $this->hasMany(DesvinculacionPaso::class);
    }
}
