<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Enums\MotivoEntregaEpp;

class EppEntrega extends Model
{
    protected $table = 'epp_entregas';

    protected $fillable = [
        'servidor_id', 'equipo_proteccion_id', 'fecha_entrega', 'cantidad',
        'motivo', 'entregado_por', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_entrega' => 'date',
            'cantidad' => 'integer',
            'motivo' => MotivoEntregaEpp::class,
        ];
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }

    public function equipoProteccion(): BelongsTo
    {
        return $this->belongsTo(EquipoProteccion::class);
    }

    public function entregador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }
}
