<?php

namespace App\Models\Reporte;

use App\Enums\ClaseAccionPersonal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qué clase de acto se reporta al SIITH y al SUT (fase 7.5). Hasta la fase 7.5
 * la llave era el tipo de movimiento de la taxonomía antigua; ahora es la
 * clase, y `tipo_movimiento` queda sin uso.
 *
 * @property int $id
 * @property ?ClaseAccionPersonal $clase
 * @property bool $reportable_siith
 * @property bool $reportable_sut
 * @property ?int $actualizado_por
 */
class ConfiguracionReporteMovimiento extends Model
{
    protected $table = 'configuracion_reporte_movimiento';

    protected $fillable = [
        'clase',
        'reportable_siith',
        'reportable_sut',
        'descripcion',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'clase'            => ClaseAccionPersonal::class,
            'reportable_siith' => 'boolean',
            'reportable_sut'   => 'boolean',
        ];
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }
}
