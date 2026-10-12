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
        // La liquidación de vacaciones, confirmada por el Director (fase 7.4b).
        'vacaciones_dias', 'vacaciones_remuneracion', 'vacaciones_monto', 'vacaciones_detalle',
        'vacaciones_confirmada_por', 'vacaciones_confirmada_en',
        // La indemnización, dentro del tope del Mandato 2, y su devolución.
        'indemnizacion_concepto', 'indemnizacion_monto', 'indemnizacion_resolucion', 'indemnizacion_tope',
        'indemnizacion_registrada_por', 'indemnizacion_registrada_en',
        'indemnizacion_devuelta_en', 'indemnizacion_devolucion_documento', 'indemnizacion_devolucion_registrada_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_salida'                => 'date',
            'cerrada_en'                  => 'datetime',
            'cancelada_en'                => 'datetime',
            'vacaciones_dias'             => 'decimal:2',
            'vacaciones_remuneracion'     => 'decimal:2',
            'vacaciones_monto'            => 'decimal:2',
            'vacaciones_detalle'          => 'array',
            'vacaciones_confirmada_en'    => 'datetime',
            'indemnizacion_monto'         => 'decimal:2',
            'indemnizacion_tope'          => 'decimal:2',
            'indemnizacion_registrada_en' => 'datetime',
            'indemnizacion_devuelta_en'   => 'date',
        ];
    }

    /** ¿Cobró una indemnización y no la devolvió? No reingresa (Mandato 2, art. 8). */
    public function prohibeElReingreso(): bool
    {
        return $this->estado !== self::CANCELADA
            && $this->indemnizacion_registrada_en !== null
            && (float) $this->indemnizacion_monto > 0
            && $this->indemnizacion_devuelta_en === null;
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
