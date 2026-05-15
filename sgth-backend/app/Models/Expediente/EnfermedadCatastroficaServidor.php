<?php

namespace App\Models\Expediente;

use App\Observers\Expediente\EnfermedadCatastroficaServidorObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(EnfermedadCatastroficaServidorObserver::class)]
class EnfermedadCatastroficaServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'enfermedades_catastroficas_servidor';

    protected $fillable = [
        'servidor_id',
        'tipo_enfermedad',
        'codigo_cie10',
        'certificado_ruta',
        'certificado_nombre_archivo',
        'fecha_diagnostico',
    ];

    protected function casts(): array
    {
        return [
            'fecha_diagnostico' => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
