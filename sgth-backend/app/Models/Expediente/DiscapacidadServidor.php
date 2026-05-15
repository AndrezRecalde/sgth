<?php

namespace App\Models\Expediente;

use App\Enums\TipoDiscapacidad;
use App\Observers\Expediente\DiscapacidadServidorObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(DiscapacidadServidorObserver::class)]
class DiscapacidadServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'discapacidades_servidor';

    protected $fillable = [
        'servidor_id',
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
            'tipo_discapacidad'  => TipoDiscapacidad::class,
            'porcentaje'         => 'decimal:2',
            'carnet_vencimiento' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
