<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticoSecundarioConsulta extends Model
{
    use HasFactory;

    protected $table = 'diagnosticos_secundarios_consulta';

    protected $fillable = [
        'consulta_medica_id',
        'diagnostico_cie10_id',
    ];

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function diagnostico(): BelongsTo
    {
        return $this->belongsTo(
            DiagnosticoCie10::class, 'diagnostico_cie10_id'
        );
    }
}
