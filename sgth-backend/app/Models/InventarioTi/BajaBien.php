<?php

namespace App\Models\InventarioTi;

use App\Enums\EstadoBaja;
use App\Enums\MotivoBaja;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La baja de un equipo: el informe técnico que la propone, la aprobación del
 * jefe de TIC con su memorando y la entrega a Activos Fijos y Bodega.
 */
class BajaBien extends Model
{
    protected $table = 'bajas_bien';

    protected $fillable = [
        'bien_informatico_id', 'estado', 'motivo', 'diagnostico', 'recomendacion', 'fecha_informe',
        'informado_por', 'baja_principal_id', 'fecha_baja', 'aprobado_por', 'aprobado_en',
        'memorando_numero', 'memorando_archivo', 'memorando_nombre', 'soporte_anterior', 'entregado_activos_en',
        'rechazado_por', 'rechazado_en', 'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'estado'               => EstadoBaja::class,
            'motivo'               => MotivoBaja::class,
            'fecha_informe'        => 'date',
            'fecha_baja'           => 'date',
            'aprobado_en'          => 'datetime',
            'rechazado_en'         => 'datetime',
            'entregado_activos_en' => 'date',
        ];
    }

    public function bien(): BelongsTo
    {
        return $this->belongsTo(BienInformatico::class, 'bien_informatico_id');
    }

    public function informadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'informado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function rechazadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rechazado_por');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'baja_principal_id');
    }

    /** Las bajas de los componentes que salieron junto con este equipo. */
    public function componentes(): HasMany
    {
        return $this->hasMany(self::class, 'baja_principal_id');
    }

    /** El número del informe técnico: IB-000001. */
    public function numero(): string
    {
        return 'IB-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
