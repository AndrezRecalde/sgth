<?php

namespace App\Models\Disciplinario;

use App\Enums\TipoSancion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Una falta del reglamento interno de trabajo de los obreros (diseño de
 * Acciones de Personal, 9.15; fase 4.4). Talento Humano carga el catálogo con
 * su copia del reglamento aprobado por el Ministerio del Trabajo.
 *
 * Sin ella no se sanciona a un obrero: el Código del Trabajo prohíbe imponer
 * multas que el reglamento interno no prevea (Art. 44 a). Cada falta dice qué
 * sanciones admite —amonestación verbal, escrita o multa— y hasta qué
 * porcentaje la multa, nunca más del 10 %. Las que el reglamento manda al
 * visto bueno no van aquí: son las causales del Art. 172.
 */
class FaltaReglamentoInterno extends Model
{
    protected $table = 'faltas_reglamento_interno';

    /** El tope de la multa del obrero: el 10 % de la remuneración. */
    public const MULTA_MAXIMA = 10.0;

    protected $fillable = [
        'articulo',
        'descripcion',
        'gravedad',
        'admite_amonestacion_verbal',
        'admite_amonestacion_escrita',
        'admite_multa',
        'porcentaje_maximo_multa',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'admite_amonestacion_verbal'  => 'boolean',
            'admite_amonestacion_escrita' => 'boolean',
            'admite_multa'                => 'boolean',
            'porcentaje_maximo_multa'     => 'decimal:2',
            'activo'                      => 'boolean',
        ];
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** @return list<TipoSancion> */
    public function sancionesAdmitidas(): array
    {
        return array_values(array_filter([
            $this->admite_amonestacion_verbal ? TipoSancion::AMONESTACION_VERBAL : null,
            $this->admite_amonestacion_escrita ? TipoSancion::AMONESTACION_ESCRITA : null,
            $this->admite_multa ? TipoSancion::MULTA : null,
        ]));
    }

    public function admite(TipoSancion $sancion): bool
    {
        return in_array($sancion, $this->sancionesAdmitidas(), true);
    }

    /** ¿Ya se imputó en un procedimiento? Entonces no se reescribe. */
    public function enUso(): bool
    {
        return Sumario::where('falta_reglamento_id', $this->id)->exists()
            || SancionDisciplinaria::withTrashed()->where('falta_reglamento_id', $this->id)->exists();
    }

    /** Cómo se cita: «Art. 45, núm. 3 — Atrasos reiterados…». */
    public function cita(): string
    {
        return "{$this->articulo} del Reglamento Interno de Trabajo";
    }
}
