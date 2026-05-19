<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlergiaPaciente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'alergias_paciente';

    protected $fillable = [
        'historia_clinica_id',
        'tipo',
        'descripcion',
        'severidad',
        'observacion',
    ];

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }
}
