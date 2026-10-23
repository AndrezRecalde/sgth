<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResultadoMedico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'resultados_medicos';

    protected $fillable = [
        'historia_clinica_id',
        'consulta_medica_id',
        'orden_examen_id',
        'subido_por',
        'tipo',
        'descripcion',
        'archivo',
        'fecha_resultado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_resultado' => 'date',
        ];
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function consultaMedica(): BelongsTo
    {
        return $this->belongsTo(ConsultaMedica::class);
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    /** La orden de la que vino, si vino de una. */
    public function ordenExamen(): BelongsTo
    {
        return $this->belongsTo(OrdenExamen::class);
    }

    /** Los exámenes de la orden que cubre este archivo. */
    public function itemsOrden(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemOrdenExamen::class, 'resultado_orden_items', 'resultado_medico_id', 'orden_examen_item_id'
        )->orderBy('orden_examen_items.id');
    }
}
