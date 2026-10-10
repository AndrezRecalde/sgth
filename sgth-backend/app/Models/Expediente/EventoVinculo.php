<?php

namespace App\Models\Expediente;

use App\Enums\TipoEventoVinculo;
use App\Exceptions\ReglaNegocioException;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una entrada de la bitácora del vínculo laboral: algo que le pasó al contrato
 * sin ser un acto administrativo (ver TipoEventoVinculo).
 *
 * Es una bitácora: se escribe una vez y no se corrige. Si algo quedó mal
 * anotado, se anota otra cosa encima; no se reescribe lo que pasó.
 */
class EventoVinculo extends Model
{
    protected $table = 'eventos_vinculo';

    protected $fillable = [
        'servidor_id',
        'contrato_servidor_id',
        'subrogacion_id',
        'movimiento_personal_id',
        'tipo',
        'fecha',
        'descripcion',
        'datos',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'tipo'  => TipoEventoVinculo::class,
            'fecha' => 'date',
            'datos' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new ReglaNegocioException(
                'La bitácora del vínculo no se edita: se anota un evento nuevo.'
            );
        });
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(ContratoServidor::class, 'contrato_servidor_id');
    }

    public function subrogacion(): BelongsTo
    {
        return $this->belongsTo(Subrogacion::class);
    }

    /** La acción de personal a la que se refiere, si la hay: la de la subrogación. */
    public function movimientoPersonal(): BelongsTo
    {
        return $this->belongsTo(MovimientoPersonal::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
