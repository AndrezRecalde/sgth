<?php

namespace App\Models\Nomina;

use App\Enums\EstadoDescuentoRecurrente;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Nomina\DescuentoRecurrenteObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(DescuentoRecurrenteObserver::class)]
class DescuentoRecurrente extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'descuentos_recurrentes';

    protected $fillable = [
        'servidor_id',
        'concepto_nomina_id',
        'valor_cuota',
        'numero_cuotas_total',
        'numero_cuotas_pagadas',
        'fecha_inicio',
        'fecha_fin',
        'referencia_externa',
        'estado',
        'observacion',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'valor_cuota'           => 'float',
            'numero_cuotas_total'   => 'integer',
            'numero_cuotas_pagadas' => 'integer',
            'fecha_inicio'          => 'date',
            'fecha_fin'             => 'date',
            'estado'                => EstadoDescuentoRecurrente::class,
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoNomina::class, 'concepto_nomina_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
