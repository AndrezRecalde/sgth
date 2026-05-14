<?php
namespace App\Models\Bienestar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ActividadBienestar extends Model
{
    use SoftDeletes;
    protected $table = 'actividades_bienestar';
    protected $fillable = ['plan_bienestar_id', 'nombre', 'descripcion', 'fecha_inicio', 'fecha_fin', 'estado'];
    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }
    public function plan()
    {
        return $this->belongsTo(PlanBienestar::class, 'plan_bienestar_id');
    }
}