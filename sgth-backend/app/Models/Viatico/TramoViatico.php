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
        'catalogo_transporte_id', 'empresa_transporte_id',
        'datetime_salida', 'datetime_llegada',
        'orden', 'tipo_tramo',
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
    /** El tipo de transporte: lo tiene todo tramo, tenga o no empresa. */
    public function catalogo(): BelongsTo
    {
        return $this->belongsTo(CatalogoTransporte::class, 'catalogo_transporte_id');
    }

    /** Opcional: un vehículo institucional o un taxi no tienen empresa. */
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

    /**
     * Cómo se viaja este tramo, escrito para leerse: «Aéreo», «Marítimo».
     *
     * Los PDF imprimían `tipo_vehiculo` en crudo y en mayúsculas, así que la
     * columna del itinerario decía «AEREO» y «MARITIMO», sin tilde. El tipo lo
     * trae el catálogo del tramo; los tramos viejos, guardados cuando el
     * catálogo colgaba de la empresa, lo traen por ahí.
     *
     * No va en `$appends`: es para los documentos, no para la API.
     */
    public function getTipoTransporteAttribute(): string
    {
        $tipo = $this->catalogo?->tipo_vehiculo
            ?? $this->empresa?->catalogo?->tipo_vehiculo
            ?? 'terrestre';

        return match ($tipo) {
            'terrestre' => 'Terrestre',
            'aereo'     => 'Aéreo',
            'maritimo'  => 'Marítimo',
            'otro'      => 'Otro',
            default     => ucfirst($tipo),
        };
    }
}
