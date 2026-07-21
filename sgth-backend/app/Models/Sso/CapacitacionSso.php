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
            'estado' => 'boolean',
        ];
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoSso::class, 'documentable');
    }
}
