<?php

namespace App\Models\Asistencia;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántos días tomó una vacación de un período concreto.
 *
 * Una vacación puede repartirse entre varios períodos —primero se gasta el
 * saldo más antiguo—, así que hace falta una fila por período para poder
 * devolver cada día a su sitio al anularla.
 */
class VacacionDescuento extends Model
{
    protected $table = 'vacacion_descuentos';

    protected $fillable = [
        'vacacion_id',
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

    public function vacacion(): BelongsTo
    {
        return $this->belongsTo(Vacacion::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(PeriodoVacacion::class, 'periodo_vacacion_id');
    }
}
