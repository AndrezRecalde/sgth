<?php

namespace App\Models\InventarioTi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un equipo en una constatación: cómo se encontró, o que no apareció. */
class ConstatacionItem extends Model
{
    public const RESULTADOS = [
        'en_su_lugar'    => 'En su lugar',
        'otra_ubicacion' => 'Registrado en otra ubicación',
        'nuevo'          => 'Registrado en la constatación',
        'de_baja'        => 'Estaba dado de baja',
        'no_encontrado'  => 'No encontrado',
    ];

    protected $table = 'constatacion_items';

    protected $fillable = [
        'constatacion_id', 'bien_informatico_id', 'resultado', 'ubicacion_registrada_id', 'movido',
        'observacion', 'escaneado_por', 'escaneado_en',
    ];

    protected function casts(): array
    {
        return ['movido' => 'boolean', 'escaneado_en' => 'datetime'];
    }

    public function constatacion(): BelongsTo
    {
        return $this->belongsTo(Constatacion::class);
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id')->withTrashed();
    }

    public function ubicacionRegistrada(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class, 'ubicacion_registrada_id')->withTrashed();
    }

    public function escaneadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escaneado_por');
    }
}
