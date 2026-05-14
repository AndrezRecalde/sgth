<?php

namespace App\Models\Sgd;

use App\Models\User;
use App\Observers\Sgd\DocumentoInstitucionalObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(DocumentoInstitucionalObserver::class)]
class DocumentoInstitucional extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'documentos_institucionales';

    protected $fillable = [
        'tramite_id',
        'nombre',
        'ruta_archivo',
        'tipo_mime',
        'es_confidencial',
        'publicar_lotaip',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'es_confidencial' => 'boolean',
            'publicar_lotaip' => 'boolean',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class, 'tramite_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
