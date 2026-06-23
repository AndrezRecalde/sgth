<?php
namespace App\Models\Dispensario;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class ConsultaMedica extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'consultas_medicas';

    protected $fillable = [
        'historia_clinica_id', 'medico_id', 'fecha_consulta', 'hora_consulta',
        'motivo_consulta', 'examen_fisico', 'diagnostico_detallado',
        'diagnostico_cie10', 'plan_tratamiento', 'notas_medico', 'estado',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'motivo_consulta'       => 'encrypted',
            'examen_fisico'         => 'encrypted',
            'diagnostico_detallado' => 'encrypted',
            'plan_tratamiento'      => 'encrypted',
            'notas_medico'          => 'encrypted',
            'fecha_consulta'        => 'date',
            'estado'                => 'boolean',
        ];
    }

    public function historiaClinica(): BelongsTo
    {
        return $this->belongsTo(HistoriaClinica::class);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'medico_id');
    }

    public function recetasMedicas(): HasMany
    {
        return $this->hasMany(RecetaMedica::class);
    }
}
