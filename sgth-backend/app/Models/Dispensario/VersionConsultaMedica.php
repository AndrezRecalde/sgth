<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que decía una consulta antes de una corrección.
 *
 * Sin SoftDeletes a propósito: es auditoría de un registro clínico.
 */
class VersionConsultaMedica extends Model
{
    use HasFactory;

    protected $table = 'versiones_consulta_medica';

    protected $fillable = [
        'consulta_medica_id',
        'tipo_atencion', 'tipo_diagnostico',
        'motivo_consulta', 'enfermedad_actual', 'examen_fisico',
        'diagnostico_detallado', 'plan_tratamiento', 'notas_medico',
        'diagnostico_cie10_id', 'diagnosticos_secundarios',
        'reemplazada_por',
    ];

    protected function casts(): array
    {
        return [
            // El mismo cifrado que en la consulta: es el mismo dato clínico,
            // solo que en su forma anterior.
            'motivo_consulta'          => 'encrypted',
            'enfermedad_actual'        => 'encrypted',
            'examen_fisico'            => 'encrypted',
            'diagnostico_detallado'    => 'encrypted',
            'plan_tratamiento'         => 'encrypted',
            'notas_medico'             => 'encrypted',
            'diagnosticos_secundarios' => 'array',
        ];
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    /** Quien guardó la corrección que dejó atrás esta versión. */
    public function autorDelCambio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reemplazada_por');
    }

    public function diagnosticoCie10(): BelongsTo
    {
        return $this->belongsTo(DiagnosticoCie10::class, 'diagnostico_cie10_id');
    }
}
