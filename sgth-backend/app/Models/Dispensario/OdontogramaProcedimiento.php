<?php

namespace App\Models\Dispensario;

use App\Enums\ProcedimientoOdontologico;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OdontogramaProcedimiento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'odontograma_procedimientos';

    protected $fillable = [
        'odontograma_pieza_id',
        'consulta_medica_id',
        'procedimiento',
        'superficie',
        'observaciones',
        'realizado_por',
        'fecha',
        'created_by',
        'anulado_en',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'procedimiento' => ProcedimientoOdontologico::class,
            'observaciones' => 'encrypted',
            'fecha' => 'date',
            'anulado_en' => 'datetime',
        ];
    }

    public function odontogramaPieza(): BelongsTo
    {
        return $this->belongsTo(OdontogramaPieza::class);
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function realizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'realizado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
