<?php

namespace App\Models\Expediente;

use App\Enums\CausaLicencia;
use App\Models\Asistencia\Licencia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una cláusula del contrato colectivo que toca licencias (diseño de Acciones
 * de Personal, 9.15; fase 5.3).
 *
 * O amplía los días de una causa de la ley (`causa`, con su `alcance` si la
 * causa distingue casos), o crea una causa que la ley no tiene (`nombre`). Los
 * días son por licencia; `dias_por_anio`, si lo hay, es lo que el obrero puede
 * tomar por esa cláusula en un año calendario.
 *
 * Vale para los obreros mientras rija el contrato con el dictamen de Finanzas.
 * La que ya respalda una licencia no se cambia ni se borra: la licencia dice
 * lo que se le concedió con ella.
 */
class ClausulaDeLicencia extends Model
{
    use LogsActivity;

    protected $table = 'clausulas_de_licencia';

    protected $fillable = [
        'contrato_colectivo_id',
        'clausula',
        'causa',
        'alcance',
        'nombre',
        'dias',
        'dias_por_anio',
        'observacion',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'causa'         => CausaLicencia::class,
            'dias'          => 'integer',
            'dias_por_anio' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['clausula', 'causa', 'alcance', 'nombre', 'dias', 'dias_por_anio'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** ¿Crea una causa que la ley no tiene, o amplía una de la ley? */
    public function esCausaNueva(): bool
    {
        return $this->causa === null;
    }

    /** Su nombre: el que le da el contrato, o el de la causa que amplía. */
    public function etiqueta(): string
    {
        if ($this->esCausaNueva()) {
            return (string) $this->nombre;
        }

        $caso = $this->alcance ? ($this->causa->alcances()[$this->alcance]['etiqueta'] ?? null) : null;

        return $this->causa->etiqueta().($caso ? " ({$caso})" : '');
    }

    /** «Contrato colectivo N.º …, cláusula …». */
    public function baseLegal(): string
    {
        $numero = $this->contratoColectivo?->numero;

        return 'Contrato colectivo'.($numero ? " N.º {$numero}" : '').", {$this->clausula}";
    }

    public function contratoColectivo(): BelongsTo
    {
        return $this->belongsTo(ContratoColectivo::class);
    }

    public function licencias(): HasMany
    {
        return $this->hasMany(Licencia::class);
    }
}
