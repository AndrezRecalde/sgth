<?php

namespace App\Models\Nomina;

use App\Enums\EstadoNomina;
use App\Models\User;
use App\Observers\Nomina\NominaObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(NominaObserver::class)]
class Nomina extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'nominas';

    protected $fillable = [
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'total_ingresos',
        'total_descuentos',
        'total_neto',
        'cerrado_por',
        'cerrado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin'    => 'date',
            'estado'       => EstadoNomina::class,
            'cerrado_en'   => 'datetime',
        ];
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleNomina::class);
    }

    public function rolesPago(): HasMany
    {
        return $this->hasMany(RolPago::class);
    }
}
