<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolicitudConstantesVitales extends Model
{
    protected $table = 'solicitud_constantes_vitales';

    protected $fillable = [
        'solicitud_id',
        'enfermera_id',
        'peso_kg',
        'talla_cm',
        'imc',
        'temperatura_c',
        'presion_sistolica',
        'presion_diastolica',
        'frecuencia_cardiaca',
        'frecuencia_respiratoria',
        'saturacion_oxigeno',
        'glucosa',
        'observaciones_enfermera',
        'registrado_en',
    ];

    protected $casts = [
        'solicitud_id' => 'integer',
        'enfermera_id' => 'integer',
        'peso_kg' => 'decimal:2',
        'talla_cm' => 'decimal:2',
        'imc' => 'decimal:2',
        'temperatura_c' => 'decimal:2',
        'presion_sistolica' => 'integer',
        'presion_diastolica' => 'integer',
        'frecuencia_cardiaca' => 'integer',
        'frecuencia_respiratoria' => 'integer',
        'saturacion_oxigeno' => 'decimal:1',
        'glucosa' => 'decimal:2',
        'registrado_en' => 'datetime',
    ];

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudCertificacionMedica::class, 'solicitud_id');
    }

    public function enfermera(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enfermera_id');
    }
}
