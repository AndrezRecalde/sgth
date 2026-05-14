<?php

namespace App\Models\Viatico;

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comision extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comisiones';

    protected $fillable = [
        'codigo_comision',
        'motivo',
        'unidad_administrativa_id',
        'fecha_inicio',
        'fecha_fin',
        'documento_autorizacion',
        'estado',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
        ];
    }

    public function viaticos(): HasMany
    {
        return $this->hasMany(Viatico::class);
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
