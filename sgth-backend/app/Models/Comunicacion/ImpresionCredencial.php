<?php

namespace App\Models\Comunicacion;

use App\Enums\FormatoImpresionCredencial;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una vez que se imprimió una credencial (docs/diseno-comunicacion-social.md,
 * 6.1). Guarda lo que quedó en la tarjeta: si después cambia el cargo, la
 * unidad, la foto o el teléfono del contacto de emergencia, la tarjeta quedó
 * desactualizada aunque su QR siga validando.
 *
 * No se edita ni se borra: es el registro de lo que se entregó.
 */
class ImpresionCredencial extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'impresiones_credencial';

    protected $fillable = ['credencial_id', 'impresa_por', 'formato', 'lote', 'datos_impresos'];

    protected function casts(): array
    {
        return [
            'formato'        => FormatoImpresionCredencial::class,
            'datos_impresos' => 'array',
        ];
    }

    public function credencial(): BelongsTo
    {
        return $this->belongsTo(Credencial::class);
    }

    public function impresaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impresa_por');
    }
}
