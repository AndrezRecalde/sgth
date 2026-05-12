<?php

namespace App\Models\Asistencia;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeriadoInstitucional extends Model
{
    use HasFactory;

    protected $table = 'feriados_institucionales';

    protected $fillable = [
        'fecha',
        'descripcion',
        'es_nacional',
    ];

    protected function casts(): array
    {
        return [
            'fecha'       => 'date',
            'es_nacional' => 'boolean',
        ];
    }
}
