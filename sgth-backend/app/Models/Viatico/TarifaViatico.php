<?php

namespace App\Models\Viatico;

use App\Enums\ZonaViatico;
use App\Models\User;
use App\Observers\Viatico\TarifaViaticoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(TarifaViaticoObserver::class)]
class TarifaViatico extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tarifas_viatico';

    protected $fillable = [
        'zona',
        'nivel',
        'valor_diario',
        'pais_destino',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'zona'         => ZonaViatico::class,
            'valor_diario' => 'decimal:2',
        ];
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
