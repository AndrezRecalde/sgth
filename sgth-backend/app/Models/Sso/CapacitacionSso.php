<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Observers\Sso\CapacitacionSsoObserver;

#[ObservedBy(CapacitacionSsoObserver::class)]
class CapacitacionSso extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'capacitaciones_sso';

    protected $fillable = [
        'tema', 'fecha', 'duracion_horas', 'instructor',
        'lugar', 'estado', 'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            // `float` y no `decimal:2`: el cast decimal de Laravel devuelve una
            // CADENA, y esta cifra se suma en `horas_capacitacion_total` de los
            // índices proactivos. Sumar cadenas funciona en PHP por coerción,
            // pero deja el tipo del API en `string` y obliga al frontend a
            // convertir lo que debería llegar como número.
            'duracion_horas' => 'float',
            'estado' => 'boolean',
        ];
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoSso::class, 'documentable');
    }
}
