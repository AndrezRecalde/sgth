<?php

namespace App\Models\Viatico;

use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quién firmó un documento del viático, con el cargo que ejercía entonces.
 *
 * @mixin \App\Models\Viatico\ViaticoFirmante
 */
class ViaticoFirmante extends Model
{
    public $timestamps = false;

    protected $table = 'viatico_firmantes';

    protected $fillable = [
        'viatico_id',
        'documento',
        'rol',
        'servidor_id',
        'nombre',
        'cedula',
        'cargo',
        'subrogado',
        'aviso',
        'sellado_en',
    ];

    protected function casts(): array
    {
        return [
            'subrogado'  => 'boolean',
            'sellado_en' => 'datetime',
        ];
    }

    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class, 'viatico_id');
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class, 'servidor_id');
    }
}
