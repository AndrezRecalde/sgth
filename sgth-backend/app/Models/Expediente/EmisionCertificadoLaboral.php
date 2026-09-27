<?php

namespace App\Models\Expediente;

use App\Enums\TipoCertificadoLaboral;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un certificado laboral que se emitió. Ver la migración para el porqué de la
 * bitácora.
 *
 * Sin SoftDeletes: una emisión ocurrió o no ocurrió. Si se borrara, el código
 * impreso en un papel que ya está en un banco dejaría de verificar.
 */
class EmisionCertificadoLaboral extends Model
{
    protected $table = 'emisiones_certificado_laboral';

    protected $fillable = [
        'codigo', 'servidor_id', 'tipo', 'con_remuneracion',
        'emitido_por', 'emitido_en', 'vence_en',
        'firmante_nombre', 'firmante_cargo', 'firmante_cedula',
        'datos',
    ];

    protected function casts(): array
    {
        return [
            'tipo'             => TipoCertificadoLaboral::class,
            'con_remuneracion' => 'boolean',
            'emitido_en'       => 'datetime',
            'vence_en'         => 'date',
            'datos'            => 'array',
        ];
    }

    /**
     * Dónde comprueba su autenticidad quien recibe el certificado.
     *
     * Vive aquí y no en cada plantilla porque son dos sitios —el PDF y el
     * correo— y ya se desincronizaron una vez: ambos apuntaban a `app.url`,
     * que es la API. La pantalla `/verificar/{codigo}` la sirve el frontend,
     * y en producción son dos dominios distintos. El PDF de permisos tropezó
     * antes con lo mismo y sus QR dieron 404 durante meses.
     */
    public function urlVerificacion(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/verificar/'.$this->codigo;
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    /** Un certificado vencido sigue existiendo: dejó de estar vigente, nada más. */
    public function estaVigente(): bool
    {
        return $this->vence_en->endOfDay()->isFuture();
    }
}
