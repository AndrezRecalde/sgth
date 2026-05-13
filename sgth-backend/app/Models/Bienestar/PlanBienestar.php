<?php
namespace App\Models\Bienestar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PlanBienestar extends Model
{
    use SoftDeletes;
    protected $table = 'planes_bienestar';
    protected $fillable = ['anio', 'presupuesto', 'estado'];
    public function actividades()
    {
        return $this->hasMany(ActividadBienestar::class, 'plan_bienestar_id');
    }
}