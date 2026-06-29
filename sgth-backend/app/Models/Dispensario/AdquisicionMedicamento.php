<?php

namespace App\Models\Dispensario;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdquisicionMedicamento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'adquisiciones_medicamentos';

    protected $fillable = [
        'folio', 'tipo', 'numero_documento',
        'proveedor_o_donante', 'fecha_adquisicion',
        'observaciones', 'documento_respaldo',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_adquisicion' => 'date',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            ItemAdquisicion::class, 'adquisicion_id'
        );
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
