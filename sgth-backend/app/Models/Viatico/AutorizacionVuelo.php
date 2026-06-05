<?php

namespace App\Models\Viatico;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutorizacionVuelo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'autorizaciones_vuelo';

    protected $fillable = [
        'tramo_viatico_id',
        'viatico_id',
        'documento_invitacion_ruta',
        'justificacion',
        'estado',
        'aprobado_por',
        'observacion_aprobador',
        'aprobado_en',
    ];

    protected function casts(): array
    {
        return [
            'aprobado_en' => 'datetime',
        ];
    }

    public function tramo(): BelongsTo
    {
        return $this->belongsTo(TramoViatico::class, 'tramo_viatico_id');
    }

    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class);
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
