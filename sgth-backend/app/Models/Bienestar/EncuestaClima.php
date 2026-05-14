<?php
namespace App\Models\Bienestar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class EncuestaClima extends Model
{
    use SoftDeletes;
    protected $table = 'encuestas_clima';
    protected $fillable = ['anio', 'titulo', 'fecha_inicio', 'fecha_fin', 'estado'];
    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }
    public function resultados()
    {
        return $this->hasMany(ResultadoClima::class, 'encuesta_id');
    }
}