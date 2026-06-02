<?php

namespace App\Models\Expediente;

use App\Enums\EstadoContrato;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Observers\Expediente\ContratoServidorObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(ContratoServidorObserver::class)]
class ContratoServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contratos_servidor';

    protected $fillable = [
        'servidor_id',
        'tipo_nombramiento',
        'numero_contrato',
        'unidad_administrativa_id',
        'puesto_id',
        'fecha_inicio',
        'fecha_fin',
        'resolucion_numero',
        'documento_ruta',
        'estado',
        'remuneracion',
        'puede_marcar'
    ];

    protected function casts(): array
    {
        return [
            'tipo_nombramiento' => TipoNombramiento::class,
            'estado'            => EstadoContrato::class,
            'fecha_inicio'      => 'date',
            'fecha_fin'         => 'date',
            'remuneracion'      => 'decimal:2',
            'puede_marcar'      => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('estado', 'vigente')
                     ->where(function ($q) {
                         $q->whereNull('fecha_fin')
                           ->orWhere('fecha_fin', '>=', now()->toDateString());
                     });
    }
}
