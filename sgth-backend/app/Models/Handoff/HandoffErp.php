<?php

namespace App\Models\Handoff;

use App\Models\User;
use App\Observers\Handoff\HandoffErpObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(HandoffErpObserver::class)]
class HandoffErp extends Model
{
    use HasFactory;

    // Registro inmutable: sin SoftDeletes

    protected $table = 'handoffs_erp';

    protected $fillable = [
        'tipo',
        'referencia_id',
        'archivo_nombre',
        'archivo_ruta',
        'hash_integridad',
        'generado_por',
        'generado_en',
        'importado_erp_en',
    ];

    protected function casts(): array
    {
        return [
            'generado_en'      => 'datetime',
            'importado_erp_en' => 'datetime',
        ];
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generado_por');
    }
}
