<?php

namespace App\Models\Dispensario;

use App\Enums\CondicionPiezaDental;
use App\Enums\DenticionTipo;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdontogramaPieza extends Model
{
    use HasFactory;

    protected $table = 'odontograma_piezas';

    protected $fillable = [
        'odontograma_id',
        'numero_pieza',
        'denticion',
        'condicion',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'numero_pieza' => 'integer',
            'denticion' => DenticionTipo::class,
            'condicion' => CondicionPiezaDental::class,
        ];
    }

    public function odontograma(): BelongsTo
    {
        return $this->belongsTo(Odontograma::class);
    }

    public function procedimientos(): HasMany
    {
        return $this->hasMany(OdontogramaProcedimiento::class)->orderBy('fecha', 'desc');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
