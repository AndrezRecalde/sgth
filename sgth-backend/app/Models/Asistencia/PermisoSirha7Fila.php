<?php

namespace App\Models\Asistencia;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila que el SGTH escribió en dbo.USER_SPEDAY de Sirha7 al aprobar un
 * permiso: una por día. Con ellas se sabe cuántas hay que retirar si el
 * permiso se revierte, y el procedimiento de retiro no borra nada si en Sirha7
 * encuentra otra cantidad.
 */
class PermisoSirha7Fila extends Model
{
    protected $table = 'permiso_sirha7_filas';

    protected $fillable = ['permiso_servidor_id', 'sirha7_id', 'inicio', 'fin'];

    protected function casts(): array
    {
        return [
            'sirha7_id' => 'integer',
            'inicio'    => 'datetime',
            'fin'       => 'datetime',
        ];
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(PermisoServidor::class, 'permiso_servidor_id');
    }
}
