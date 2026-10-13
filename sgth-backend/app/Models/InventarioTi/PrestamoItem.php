<?php

namespace App\Models\InventarioTi;

use App\Enums\CondicionFisicaBien;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un equipo dentro de un préstamo: el concreto que eligió el servidor (D13),
 * su horario, los accesorios y la condición con que sale y vuelve.
 *
 * `periodo` es una columna generada (tsrange de `inicio` a `fin`) que usa la
 * restricción de exclusión; no se escribe.
 */
class PrestamoItem extends Model
{
    protected $table = 'prestamo_items';

    protected $fillable = [
        'prestamo_equipo_id', 'bien_informatico_id', 'inicio', 'fin', 'bloquea',
        'accesorios', 'condicion_entrega', 'condicion_devolucion', 'novedad',
    ];

    protected $hidden = ['periodo'];

    protected function casts(): array
    {
        return [
            'inicio'               => 'datetime',
            'fin'                  => 'datetime',
            'bloquea'              => 'boolean',
            'condicion_entrega'    => CondicionFisicaBien::class,
            'condicion_devolucion' => CondicionFisicaBien::class,
        ];
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoEquipo::class, 'prestamo_equipo_id');
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }
}
