<?php

namespace App\Models\Dispensario;

use App\Enums\TipoExamenFemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FemoExamen extends Model
{
    protected $table = 'femo_examenes';

    protected $fillable = [
        'ficha_id', 'resultado_medico_id', 'nombre_examen', 'resultado',
        'fecha_examen', 'tipo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_examen' => 'date',
            'tipo' => TipoExamenFemo::class,
        ];
    }

    public function ficha(): BelongsTo
    {
        return $this->belongsTo(FichaSaludOcupacional::class, 'ficha_id');
    }

    /** El resultado del Dispensario del que se copió, si salió de uno. */
    public function resultadoMedico(): BelongsTo
    {
        return $this->belongsTo(ResultadoMedico::class);
    }
}
