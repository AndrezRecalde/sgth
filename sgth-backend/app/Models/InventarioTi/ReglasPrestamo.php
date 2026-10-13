<?php

namespace App\Models\InventarioTi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Las reglas del préstamo que TIC configura (D16). Es una sola fila, que crea
 * la migración con los valores por defecto.
 */
class ReglasPrestamo extends Model
{
    protected $table = 'reglas_prestamo';

    protected $fillable = [
        'plazo_maximo_dias_habiles', 'anticipacion_minima_minutos', 'hora_apertura', 'hora_cierre',
        'presta_fines_de_semana', 'bloquea_con_vencido', 'margen_retiro_minutos', 'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'plazo_maximo_dias_habiles'   => 'integer',
            'anticipacion_minima_minutos' => 'integer',
            'presta_fines_de_semana'      => 'boolean',
            'bloquea_con_vencido'         => 'boolean',
            'margen_retiro_minutos'       => 'integer',
        ];
    }

    /** La fila de las reglas; si alguien la borró, vuelve con los valores por defecto. */
    public static function vigentes(): self
    {
        return self::query()->orderBy('id')->first() ?? self::create([])->refresh();
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** «08:00:00» → «08:00». */
    public function apertura(): string
    {
        return substr((string) $this->hora_apertura, 0, 5);
    }

    public function cierre(): string
    {
        return substr((string) $this->hora_cierre, 0, 5);
    }
}
