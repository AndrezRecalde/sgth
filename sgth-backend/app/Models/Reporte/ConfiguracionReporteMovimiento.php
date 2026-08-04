<?php

namespace App\Models\Reporte;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionReporteMovimiento extends Model
{
    protected $table = 'configuracion_reporte_movimiento';

    protected $fillable = [
        'tipo_movimiento',
        'reportable_siith',
        'reportable_sut',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'reportable_siith' => 'boolean',
            'reportable_sut'   => 'boolean',
        ];
    }
}
