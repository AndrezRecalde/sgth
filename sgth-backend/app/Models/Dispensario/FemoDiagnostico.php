<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoDiagnostico extends Model
{
    protected $table = 'femo_diagnosticos';

    protected $fillable = [
        'ficha_id', 'diagnostico_cie10_id', 'tipo', 'orden',
    ];

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }

    public function diagnostico(): BelongsTo
    {
        return $this->belongsTo(DiagnosticoCie10::class, 'diagnostico_cie10_id');
    }
}
