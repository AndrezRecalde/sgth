<?php

namespace App\Models\Asistencia;

use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Marcacion extends Model
{
    use HasFactory;

    protected $table = 'marcaciones';

    // Registro histórico inmutable desde el biométrico: SIN SoftDeletes

    protected $fillable = [
        'servidor_id',
        'fecha_hora',
        'tipo',
        'dispositivo_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            // El tipo ('entrada'/'salida') se maneja nativamente como string
        ];
    }

    // Relaciones
    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
