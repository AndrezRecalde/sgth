<?php

namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AntecedentePaciente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'antecedentes_paciente';

    protected $fillable = [
        'historia_clinica_id',
        'tipo',
        'descripcion',
        'fecha_aproximada',
    ];

    protected $casts = [
        'fecha_aproximada' => 'integer',
    ];

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }
}
