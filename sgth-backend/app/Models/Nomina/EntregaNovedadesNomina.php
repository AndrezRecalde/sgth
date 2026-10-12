<?php

namespace App\Models\Nomina;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La entrega de las novedades de un mes a Financiero (fase 7.3a): congela lo
 * que se le dio, y desde entonces lo que cambie en ese mes va como ajuste en el
 * siguiente. Se entrega una vez y no se deshace: ya salió el correo.
 *
 * @property int $id
 * @property string $periodo
 * @property int $entregada_por
 * @property \Illuminate\Support\Carbon $entregada_en
 * @property int $novedades
 * @property int $ajustes
 * @property ?string $destinatario
 * @property ?string $aviso
 */
class EntregaNovedadesNomina extends Model
{
    protected $table = 'entregas_novedades_nomina';

    protected $fillable = [
        'periodo', 'entregada_por', 'entregada_en', 'novedades', 'ajustes', 'destinatario', 'aviso',
    ];

    protected function casts(): array
    {
        return [
            'entregada_en' => 'datetime',
            'novedades'    => 'integer',
            'ajustes'      => 'integer',
        ];
    }

    public function entregadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregada_por');
    }

    public function filas(): HasMany
    {
        return $this->hasMany(NovedadNomina::class, 'entrega_id');
    }
}
