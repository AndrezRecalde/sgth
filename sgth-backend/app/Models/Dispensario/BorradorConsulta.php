<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que el médico lleva escrito de una consulta que todavía no ha guardado.
 *
 * No es historia clínica: no se versiona, no se anula y no aparece en el
 * historial del paciente. Existe para que una sesión caducada no se lleve por
 * delante lo escrito, y desaparece en cuanto la consulta se registra.
 */
class BorradorConsulta extends Model
{
    protected $table = 'borradores_consulta';

    protected $fillable = [
        'agenda_medica_id',
        'medico_id',
        'contenido',
    ];

    protected function casts(): array
    {
        return [
            'contenido' => 'encrypted:array',
        ];
    }

    public function agendaMedica(): BelongsTo
    {
        return $this->belongsTo(AgendaMedica::class);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }
}
