<?php

namespace App\Models\Expediente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * El acta de finiquito de la terminación de un obrero (diseño de Acciones de
 * Personal, 9.15; fase 4.5). Se genera y se legaliza en el sistema del
 * Ministerio del Trabajo: aquí se registra su número, su fecha y lo pagado, y
 * el PDF si se tiene. La terminación ya registrada no se edita, así que el
 * acta va aparte, enlazada a ella.
 */
class ActaFiniquito extends Model
{
    use LogsActivity;

    protected $table = 'actas_finiquito';

    protected $fillable = [
        'movimiento_personal_id',
        'numero',
        'fecha',
        'valor_pagado',
        'documento_ruta',
        'documento_nombre',
        'observacion',
        'created_by',
        'updated_by',
    ];

    /** La ruta del archivo no sale del API: se descarga por su endpoint. */
    protected $hidden = ['documento_ruta'];

    protected $appends = ['tiene_documento'];

    protected function casts(): array
    {
        return [
            'fecha'        => 'date',
            'valor_pagado' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['numero', 'fecha', 'valor_pagado', 'documento_nombre'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class);
    }

    public function getTieneDocumentoAttribute(): bool
    {
        return filled($this->documento_ruta);
    }
}
