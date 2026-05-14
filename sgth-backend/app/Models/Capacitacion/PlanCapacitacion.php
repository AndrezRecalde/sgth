<?php
namespace App\Models\Capacitacion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanCapacitacion extends Model
{
    use SoftDeletes;

    protected $table = 'planes_capacitacion';

    protected $fillable = ['anio', 'presupuesto_total', 'estado'];

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'plan_capacitacion_id');
    }
}