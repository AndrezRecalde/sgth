<?php

namespace App\Models\Expediente;

use App\Enums\NacionalidadEstudio;
use App\Enums\NivelEstudio;
use App\Enums\TipoEstudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistorialAcademicoServidor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'historial_academico_servidor';

    protected $fillable = [
        'servidor_id',
        'tipo_estudio',
        'nivel_estudio',
        'nacionalidad_estudio',
        'institucion',
        'fecha_inicio',
        'fecha_fin',
        'titulo_capacitacion',
        'codigo_senescyt',
    ];

    protected function casts(): array
    {
        return [
            'tipo_estudio'        => TipoEstudio::class,
            'nivel_estudio'       => NivelEstudio::class,
            'nacionalidad_estudio' => NacionalidadEstudio::class,
            'fecha_inicio'        => 'date',
            'fecha_fin'           => 'date',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function estaEnCurso(): bool
    {
        return is_null($this->fecha_fin);
    }
}