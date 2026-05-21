<?php

namespace App\Models\Expediente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EnfermedadCatastroficaCargaFamiliar extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'enfermedades_catastroficas_carga_familiar';

    protected $fillable = [
        'carga_familiar_id',
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

    public function cargaFamiliar(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class);
    }
}