<?php

namespace App\Models\Dispensario;

use App\Enums\EstadoEnvioResultado;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un resultado que el servidor envió desde el Portal contra su orden, por
 * validar hasta que un médico lo acepte (fase 6 de docs/diseno-examenes-medicos.md).
 */
class EnvioResultado extends Model
{
    protected $table = 'envios_resultado';

    protected $fillable = [
        'orden_examen_id',
        'enviado_por',
        'items',
        'fecha_resultado',
        'comentario',
        'archivo',
        'estado',
        'motivo_rechazo',
        'atendido_por',
        'atendido_en',
        'resultado_medico_id',
    ];

    protected function casts(): array
    {
        return [
            'items'           => 'array',
            'fecha_resultado' => 'date',
            'estado'          => EstadoEnvioResultado::class,
            'atendido_en'     => 'datetime',
        ];
    }

    public function ordenExamen(): BelongsTo
    {
        return $this->belongsTo(OrdenExamen::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    /** El resultado que nació al validarlo. */
    public function resultadoMedico(): BelongsTo
    {
        return $this->belongsTo(ResultadoMedico::class);
    }
}
