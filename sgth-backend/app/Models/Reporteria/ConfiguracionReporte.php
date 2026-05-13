<?php

namespace App\Models\Reporteria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Expediente\Servidor;

class ConfiguracionReporte extends Model
{
    use SoftDeletes;

    protected $table = 'configuraciones_reporte';

    protected $fillable = [
        'nombre',
        'descripcion',
        'modulo',
        'parametros',
        'creado_por_id'
    ];

    protected function casts(): array
    {
        return [
            'parametros' => 'array',
        ];
    }

    public function creador()
    {
        return $this->belongsTo(Servidor::class, 'creado_por_id');
    }
}
