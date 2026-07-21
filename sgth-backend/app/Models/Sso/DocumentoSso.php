<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\User;

class DocumentoSso extends Model
{
    use SoftDeletes;

    protected $table = 'documentos_sso';

    protected $fillable = [
        'documentable_type', 'documentable_id',
        'nombre', 'ruta_archivo', 'tipo_mime', 'tamano_bytes', 'subido_por',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }
}
