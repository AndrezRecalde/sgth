<?php

namespace App\Models\Expediente;

use App\Enums\EstadoFormacion;
use App\Enums\NacionalidadEstudio;
use App\Enums\NivelEstudio;
use App\Enums\TipoEstudio;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        // Lo que registra el servidor desde el portal llega por validar, con
        // su PDF (decisión del 2026-10-17).
        'estado',
        'documento_ruta',
        'documento_nombre',
        'registrado_por',
        'validado_por',
        'validado_en',
        'motivo_rechazo',
    ];

    /** El de la columna: lo que registra Talento Humano entra validado. */
    protected $attributes = ['estado' => 'validado'];

    /** La ruta en el disco no sale: el PDF se descarga por su endpoint. */
    protected $hidden = ['documento_ruta'];

    protected $appends = ['tiene_documento'];

    protected function casts(): array
    {
        return [
            'tipo_estudio'        => TipoEstudio::class,
            'nivel_estudio'       => NivelEstudio::class,
            'nacionalidad_estudio' => NacionalidadEstudio::class,
            'estado'              => EstadoFormacion::class,
            'fecha_inicio'        => 'date',
            'fecha_fin'           => 'date',
            'validado_en'         => 'datetime',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    protected function tieneDocumento(): Attribute
    {
        return Attribute::get(fn () => filled($this->documento_ruta));
    }
}
