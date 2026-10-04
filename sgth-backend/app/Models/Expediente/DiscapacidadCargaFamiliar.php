<?php

namespace App\Models\Expediente;

use App\Enums\TipoDiscapacidad;
use App\Observers\Expediente\CondicionCargaFamiliarObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(CondicionCargaFamiliarObserver::class)]
class DiscapacidadCargaFamiliar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'discapacidades_carga_familiar';

    protected $fillable = [
        'carga_familiar_id',
        'tipo_discapacidad',
        'porcentaje',
        'numero_carnet_conadis',
        'carnet_ruta',
        'carnet_nombre_archivo',
        'carnet_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'tipo_discapacidad' => TipoDiscapacidad::class,
            'porcentaje'        => 'decimal:2',
            'carnet_vencimiento' => 'date',
        ];
    }

    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class);
    }
}