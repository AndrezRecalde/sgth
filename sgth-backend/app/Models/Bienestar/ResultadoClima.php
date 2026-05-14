<?php
namespace App\Models\Bienestar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ResultadoClima extends Model
{
    use SoftDeletes;
    protected $table = 'resultados_clima';
    protected $fillable = ['encuesta_id', 'unidad_administrativa_id', 'liderazgo', 'comunicacion', 'trabajo_en_equipo', 'condiciones_trabajo', 'desarrollo_profesional', 'reconocimiento', 'satisfaccion_general'];
    public function encuesta()
    {
        return $this->belongsTo(EncuestaClima::class, 'encuesta_id');
    }
}