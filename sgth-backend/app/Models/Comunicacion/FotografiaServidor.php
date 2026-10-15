<?php

namespace App\Models\Comunicacion;

use App\Enums\OrigenFotografia;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Una fotografía de un servidor (docs/diseno-comunicacion-social.md, 5.1).
 *
 * La sube Comunicación Social (D5). Hay una vigente por servidor; reemplazarla
 * o retirarla no borra nada, y una anterior se puede restaurar. Los archivos
 * viven en el disco privado: `archivo` es la versión de 600×800 y `miniatura`
 * la cuadrada de 160×160 para los avatares. La foto es un dato personal
 * (LOPDP), así que solo se entrega con sesión a quien puede ver al servidor.
 */
class FotografiaServidor extends Model
{
    use LogsActivity;

    protected $table = 'fotografias_servidor';

    protected $fillable = [
        'servidor_id', 'archivo', 'miniatura', 'origen', 'vigente',
        'activada_en', 'activada_por', 'subida_por',
    ];

    protected function casts(): array
    {
        return [
            'origen'      => OrigenFotografia::class,
            'vigente'     => 'boolean',
            'activada_en' => 'datetime',
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class)->withTrashed();
    }

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por');
    }

    public function activadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activada_por');
    }

    /** Quién la subió, la reemplazó, la restauró o la retiró, y cuándo. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['servidor_id', 'origen', 'vigente', 'activada_en', 'activada_por'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
