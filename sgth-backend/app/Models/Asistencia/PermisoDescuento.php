<?php

namespace App\Models\Asistencia;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántos días tomó un permiso personal de un período concreto.
 *
 * Igual que una vacación, un permiso puede repartirse entre dos períodos
 * —primero se gasta el saldo más antiguo—, así que hace falta una fila por
 * período para devolver cada tramo a su sitio al revertir la confirmación.
 */
class PermisoDescuento extends Model
{
    protected $table = 'permiso_descuentos';

    protected $fillable = [
        'permiso_servidor_id',
        'periodo_vacacion_id',
        'dias',
        'devuelto_en',
    ];

    protected function casts(): array
    {
        return [
            'dias'        => 'float',
            'devuelto_en' => 'datetime',
        ];
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(PermisoServidor::class, 'permiso_servidor_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoVacacion::class, 'periodo_vacacion_id');
    }
}
