<?php

namespace App\Models\Expediente;

use App\Enums\PasoDesvinculacion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que Talento Humano decidió de un paso de la desvinculación (fase 7.4a):
 * hecho o no aplica, con su nota. En los pasos que se leen de otro módulo solo
 * cuenta el «no aplica»; si no, manda el dato.
 *
 * @property int $id
 * @property int $desvinculacion_id
 * @property PasoDesvinculacion $paso
 * @property string $estado
 * @property ?string $nota
 * @property ?int $registrado_por
 * @property ?\Illuminate\Support\Carbon $registrado_en
 */
class DesvinculacionPaso extends Model
{
    public const PENDIENTE = 'pendiente';
    public const HECHO = 'hecho';
    public const NO_APLICA = 'no_aplica';

    protected $table = 'desvinculacion_pasos';

    protected $fillable = ['desvinculacion_id', 'paso', 'estado', 'nota', 'registrado_por', 'registrado_en'];

    protected function casts(): array
    {
        return [
            'paso'          => PasoDesvinculacion::class,
            'registrado_en' => 'datetime',
        ];
    }

    public function desvinculacion(): BelongsTo
    {
        return $this->belongsTo(Desvinculacion::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
