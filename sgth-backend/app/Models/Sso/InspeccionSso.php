<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use App\Observers\Sso\InspeccionSsoObserver;

#[ObservedBy(InspeccionSsoObserver::class)]
class InspeccionSso extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inspecciones_sso';

    protected $fillable = [
        'unidad_administrativa_id', 'fecha_inspeccion', 'tipo_inspeccion',
        'hallazgos', 'recomendaciones', 'estado', 'inspector_id',
        'created_by', 'updated_by'
    ];

    protected function casts(): array
    {
        return [
            'fecha_inspeccion' => 'date',
            'estado' => 'boolean',
        ];
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoSso::class, 'documentable');
    }
}
