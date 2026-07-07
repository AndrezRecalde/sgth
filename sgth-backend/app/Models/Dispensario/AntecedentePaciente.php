<?php

namespace App\Models\Dispensario;

use App\Models\User;
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
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_aproximada' => 'integer',
            'anulado_en'       => 'datetime',
        ];
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function scopeActivos(
        \Illuminate\Database\Eloquent\Builder $query
    ): \Illuminate\Database\Eloquent\Builder {
        return $query->whereNull('anulado_en');
    }
}
