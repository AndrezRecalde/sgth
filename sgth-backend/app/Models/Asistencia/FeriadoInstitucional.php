<?php

namespace App\Models\Asistencia;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeriadoInstitucional extends Model
{
    use HasFactory;

    protected $table = 'feriados_institucionales';

    protected $fillable = [
        'fecha',
        'mes',
        'dia',
        'descripcion',
        'es_nacional',
        'es_movil',
    ];

    protected function casts(): array
    {
        return [
            'fecha'       => 'date',
            'mes'         => 'integer',
            'dia'         => 'integer',
            'es_nacional' => 'boolean',
            'es_movil'    => 'boolean',
        ];
    }

    public function scopeEsFeriado(Builder $query, Carbon $fecha): Builder
    {
        return $query->where(function ($q) use ($fecha) {
            // Feriados fijos — aplican todos los años
            $q->where(function ($sub) use ($fecha) {
                $sub->where('es_movil', false)
                    ->where('mes', $fecha->month)
                    ->where('dia', $fecha->day);
            })
            // Feriados móviles — fecha exacta del año específico
            ->orWhere(function ($sub) use ($fecha) {
                $sub->where('es_movil', true)
                    ->whereDate('fecha', $fecha->toDateString());
            });
        });
    }
}
