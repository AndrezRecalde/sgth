<?php

namespace App\Models\Asistencia;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila que el SGTH escribió en dbo.USER_SPEDAY de Sirha7 al aprobar una
 * licencia: una por día. Lleva la referencia de su tramo —una licencia de más
 * de 31 días se registra en varios—, porque para retirarlas el procedimiento
 * pide la referencia y cuántas filas son, y si encuentra otra cantidad no
 * borra nada.
 */
class LicenciaSirha7Fila extends Model
{
    protected $table = 'licencia_sirha7_filas';

    protected $fillable = ['licencia_id', 'sirha7_id', 'referencia', 'inicio', 'fin'];

    protected function casts(): array
    {
        return [
            'sirha7_id' => 'integer',
            'inicio'    => 'datetime',
            'fin'       => 'datetime',
        ];
    }

    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencia::class);
    }
}
