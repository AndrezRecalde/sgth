<?php

namespace App\Models\Nomina;

use App\Models\Expediente\Servidor;
use App\Observers\Nomina\RolPagoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(RolPagoObserver::class)]
class RolPago extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'roles_pago';

    protected $fillable = [
        'nomina_id',
        'servidor_id',
        'total_ingresos',
        'total_descuentos',
        'total_neto',
        'enviado_por_correo',
        'enviado_en',
    ];

    protected function casts(): array
    {
        return [
            'enviado_por_correo' => 'boolean',
            'enviado_en'         => 'datetime',
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
