<?php
namespace App\Models\Viatico;

use App\Models\Geografia\Canton;
use App\Models\Geografia\Provincia;
use App\Observers\Viatico\TramoViaticoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(TramoViaticoObserver::class)]
class TramoViatico extends Model
{
    protected $table = 'tramos_viatico';
    protected $fillable = [
        'viatico_id',
        'origen_tipo', 'origen_provincia_id', 'origen_canton_id',
        'origen_pais', 'origen_ciudad',
        'destino_tipo', 'destino_provincia_id', 'destino_canton_id',
        'destino_pais', 'destino_ciudad',
        'empresa_transporte_id',
        'datetime_salida', 'datetime_llegada',
        'orden',
    ];
    protected function casts(): array
    {
        return [
            'datetime_salida'  => 'datetime',
            'datetime_llegada' => 'datetime',
        ];
    }
    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class);
    }
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaTransporte::class,
            'empresa_transporte_id');
    }
    public function origenProvincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'origen_provincia_id');
    }
    public function origenCanton(): BelongsTo
    {
        return $this->belongsTo(Canton::class, 'origen_canton_id');
    }
    public function destinoProvincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'destino_provincia_id');
    }
    public function destinoCanton(): BelongsTo
    {
        return $this->belongsTo(Canton::class, 'destino_canton_id');
    }
    public function autorizacionVuelo(): HasOne
    {
        return $this->hasOne(AutorizacionVuelo::class,
            'tramo_viatico_id');
    }
}
