<?php

namespace App\Models\Asistencia;

use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Asistencia\VacacionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(VacacionObserver::class)]
class Vacacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vacaciones';

    protected $fillable = [
        'servidor_id',
        'fecha_inicio',
        'fecha_fin',
        'dias_solicitados',
        'tipo_dias',
        'estado',
        'aprobado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio'     => 'date',
            'fecha_fin'        => 'date',
            'dias_solicitados' => 'float',
            // tipo_dias y estado se manejarán como strings nativos por ahora
        ];
    }

    // Relaciones
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
