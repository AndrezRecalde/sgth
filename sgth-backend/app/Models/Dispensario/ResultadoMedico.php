<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultadoMedico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'resultados_medicos';

    protected $fillable = [
        'historia_clinica_id',
        'consulta_medica_id',
        'subido_por',
        'tipo',
        'descripcion',
        'archivo',
        'fecha_resultado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_resultado' => 'date',
        ];
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
