<?php

namespace App\Models\Disciplinario;

use App\Enums\EstadoSumario;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Observers\Disciplinario\SumarioObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(SumarioObserver::class)]
class Sumario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sumarios';

    protected $fillable = [
        'servidor_id',
        'motivo',
        'estado',
        'fecha_apertura',
        'notificado_sn',
        'fecha_notificacion',
        'fecha_termino_prueba',
        'fecha_informe',
        'fecha_resolucion',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado'               => EstadoSumario::class,
            'fecha_apertura'       => 'date',
            'notificado_sn'        => 'boolean',
            'fecha_notificacion'   => 'date',
            'fecha_termino_prueba' => 'date',
            'fecha_informe'        => 'date',
            'fecha_resolucion'     => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function sancion(): HasOne
    {
        return $this->hasOne(SancionDisciplinaria::class, 'sumario_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
