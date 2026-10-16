<?php

namespace App\Models\InventarioTi;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un modelo de equipo (marca y modelo) al que sirve un consumible [P1]. El
 * modelo se compara sin mayúsculas ni espacios, así que «LaserJet M551» y
 * «LASERJET  M551» son el mismo.
 */
class ConsumibleModelo extends Model
{
    protected $table = 'consumible_modelos';

    protected $fillable = ['consumible_id', 'marca_id', 'modelo', 'modelo_normalizado'];

    public static function normalizar(?string $modelo): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', (string) $modelo));
    }

    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class)->withTrashed();
    }

    /**
     * Los equipos del inventario de este modelo que siguen en servicio. Un
     * modelo sin equipos es la señal de que en alguna ficha está escrito distinto.
     */
    public function equiposQueCoinciden(): Builder
    {
        return BienInformatico::query()
            ->where('marca_id', $this->marca_id)
            ->whereRaw("lower(regexp_replace(coalesce(modelo, ''), '\\s', '', 'g')) = ?", [$this->modelo_normalizado])
            ->where('estado_operativo', '!=', 'dado_de_baja');
    }
}
