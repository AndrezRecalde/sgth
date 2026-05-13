<?php
namespace App\Models\Capacitacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
class EvaluacionCapacitacion extends Model
{
    use SoftDeletes;
    protected $table = 'evaluaciones_capacitacion';
    protected $fillable = ['inscripcion_id', 'nivel', 'calificacion', 'observaciones', 'evaluador_id'];
    public function inscripcion()
    {
        return $this->belongsTo(InscripcionCurso::class, 'inscripcion_id');
    }
    public function evaluador()
    {
        return $this->belongsTo(User::class, 'evaluador_id');
    }
}