<?php
namespace App\Models\Capacitacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Curso extends Model
{
    use SoftDeletes;
    protected $table = 'cursos';
    protected $fillable = ['plan_capacitacion_id', 'nombre', 'descripcion', 'modalidad', 'estado', 'costo_por_participante', 'fecha_inicio', 'fecha_fin', 'proveedor'];
    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }
    public function plan()
    {
        return $this->belongsTo(PlanCapacitacion::class, 'plan_capacitacion_id');
    }
    public function inscripciones()
    {
        return $this->hasMany(InscripcionCurso::class, 'curso_id');
    }
}