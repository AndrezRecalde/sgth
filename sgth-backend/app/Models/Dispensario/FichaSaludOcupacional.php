<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Sso\AccidenteTrabajo;

class FichaSaludOcupacional extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fichas_salud_ocupacional';

    protected $fillable = [
        'servidor_id', 'fecha_evaluacion', 'tipo_ficha', 'aptitud',
        'restricciones', 'observaciones', 'evaluador_id', 'accidente_trabajo_id',
        'estado', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_evaluacion' => 'date',
            'estado'           => 'boolean',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function evaluador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }

    public function accidenteTrabajo(): BelongsTo
    {
        return $this->belongsTo(AccidenteTrabajo::class);
    }
}
