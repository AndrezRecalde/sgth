<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\User;
use App\Enums\EstadoCumplimientoNormativa;

class CumplimientoNormativa extends Model
{
    protected $table = 'cumplimiento_normativa';

    protected $fillable = [
        'normativa_legal_sso_id', 'periodo', 'estado',
        'observaciones', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoCumplimientoNormativa::class,
        ];
    }

    public function normativa(): BelongsTo
    {
        return $this->belongsTo(NormativaLegalSso::class, 'normativa_legal_sso_id');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(DocumentoSso::class, 'documentable');
    }
}
