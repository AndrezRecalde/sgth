<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\TipoNormativaLegal;

class NormativaLegalSso extends Model
{
    protected $table = 'normativa_legal_sso';

    protected $fillable = [
        'nombre', 'tipo', 'fecha_vigencia', 'descripcion', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoNormativaLegal::class,
            'fecha_vigencia' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function cumplimientos(): HasMany
    {
        return $this->hasMany(CumplimientoNormativa::class);
    }
}
