<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Sso\AccidenteTrabajoObserver;
use App\Enums\TipoEventoAccidente;

#[ObservedBy(AccidenteTrabajoObserver::class)]
class AccidenteTrabajo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accidentes_trabajo';

    protected $fillable = [
        'servidor_id', 'tipo_evento', 'fecha_accidente', 'hora_accidente', 'lugar_accidente',
        'descripcion_hechos', 'gravedad', 'requirio_atencion_medica',
        'dias_reposo_medico', 'causa_raiz', 'medidas_correctivas',
        'estado', 'investigado_por', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'tipo_evento' => TipoEventoAccidente::class,
            'fecha_accidente' => 'date',
            'hora_accidente' => 'datetime:H:i',
            'requirio_atencion_medica' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function investigador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigado_por');
    }
}
