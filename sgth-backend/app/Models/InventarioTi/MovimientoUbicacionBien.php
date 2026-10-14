<?php

namespace App\Models\InventarioTi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un cambio de ubicación de un equipo: al editarlo, entregarlo, traspasarlo o en una mudanza. */
class MovimientoUbicacionBien extends Model
{
    public const MOTIVOS = [
        'edicion'  => 'Edición de la ficha',
        'entrega'  => 'Entrega en custodia',
        'traspaso' => 'Traspaso de custodia',
        'mudanza'  => 'Mudanza de la unidad',
    ];

    public const UPDATED_AT = null;

    protected $table = 'movimientos_ubicacion_bien';

    protected $fillable = ['bien_informatico_id', 'ubicacion_anterior_id', 'ubicacion_nueva_id', 'motivo', 'detalle', 'registrado_por'];

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function anterior(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_anterior_id')->withTrashed();
    }

    public function nueva(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_nueva_id')->withTrashed();
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
