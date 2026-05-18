<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Triaje extends Model
{
    use HasFactory;

    protected $table = 'triajes';

    protected $fillable = [
        'agenda_medica_id',
        'historia_clinica_id',
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
        'registrado_en'
    ];

    protected $casts = [
        'agenda_medica_id'        => 'integer',
        'historia_clinica_id'     => 'integer',
        'enfermera_id'            => 'integer',
        'peso_kg'                 => 'decimal:2',
        'talla_cm'                => 'decimal:2',
        'imc'                     => 'decimal:2',
        'temperatura_c'           => 'decimal:2',
        'presion_sistolica'       => 'integer',
        'presion_diastolica'      => 'integer',
        'frecuencia_cardiaca'     => 'integer',
        'frecuencia_respiratoria' => 'integer',
        'saturacion_oxigeno'      => 'decimal:1',
        'glucosa'                 => 'decimal:2',
        'registrado_en'           => 'datetime',
    ];

    /**
     * Calcula automáticamente el IMC si hay peso y talla
     */
    protected function imcCalculado(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->peso_kg && $this->talla_cm) {
                    $tallaMetros = $this->talla_cm / 100;
                    if ($tallaMetros > 0) {
                        return round($this->peso_kg / ($tallaMetros * $tallaMetros), 2);
                    }
                }
                return null;
            }
        );
    }

    /**
     * Clasificación del IMC
     */
    protected function clasificacionImc(): Attribute
    {
        return Attribute::make(
            get: function () {
                $imc = $this->imcCalculado;
                
                if (!$imc) {
                    return null;
                }

                if ($imc < 18.5) {
                    return 'Bajo peso';
                } elseif ($imc >= 18.5 && $imc < 25) {
                    return 'Normal';
                } elseif ($imc >= 25 && $imc < 30) {
                    return 'Sobrepeso';
                } else {
                    return 'Obesidad';
                }
            }
        );
    }

    public function agendaMedica(): BelongsTo
    {
        return $this->belongsTo(AgendaMedica::class);
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function enfermera(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enfermera_id');
    }
}
