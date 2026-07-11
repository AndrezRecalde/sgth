<?php

namespace App\Models\Seleccion;

use App\Enums\EstadoConvocatoria;
use App\Models\Estructura\Puesto;
use App\Models\User;
use App\Observers\Seleccion\ConvocatoriaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(ConvocatoriaObserver::class)]
class Convocatoria extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'puesto_id',
        'codigo',
        'titulo',
        'descripcion',
        'bases_concurso',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'tipo',
        'vacantes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'bases_concurso' => 'array',
            'fecha_inicio'   => 'date',
            'fecha_fin'      => 'date',
            'estado'         => EstadoConvocatoria::class,
            'vacantes'       => 'integer',
        ];
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    public function postulantes(): HasMany
    {
        return $this->hasMany(Postulante::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
