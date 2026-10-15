<?php

namespace App\Models\Comunicacion;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Los datos de la institución que salen en las pantallas y en los documentos
 * (docs/diseno-comunicacion-social.md, 4.5). Es una sola fila: la siembra la
 * migración con lo que antes estaba escrito en el código.
 */
class DatosInstitucionales extends Model
{
    use LogsActivity;

    protected $table = 'datos_institucionales';

    protected $fillable = [
        'nombre_oficial', 'siglas', 'nombre_corto', 'ruc', 'direccion', 'telefono',
        'sitio_web', 'actualizado_por',
    ];

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nombre_oficial', 'siglas', 'nombre_corto', 'ruc', 'direccion', 'telefono', 'sitio_web'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
