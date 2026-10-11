<?php

namespace App\Models\Expediente;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * El salario básico unificado de un año (fase 4.6). Lo fija el Ministerio del
 * Trabajo por acuerdo; aquí lo usan los topes del Mandato 2 en la liquidación
 * de los obreros, y en la fase 7 lo leerá la nómina.
 */
class SalarioBasico extends Model
{
    use LogsActivity;

    protected $table = 'salarios_basicos';

    protected $fillable = ['anio', 'monto', 'acuerdo', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'anio'  => 'integer',
            'monto' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['anio', 'monto', 'acuerdo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** El de ese año, si ya se registró. */
    public static function delAnio(int $anio): ?float
    {
        $monto = static::where('anio', $anio)->value('monto');

        return $monto !== null ? (float) $monto : null;
    }
}
