<?php

namespace App\Models\Sso;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;

class HorasTrabajadasPeriodo extends Model
{
    protected $table = 'horas_trabajadas_periodo';

    protected $fillable = [
        'periodo', 'unidad_administrativa_id', 'total_horas', 'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'total_horas' => 'integer',
        ];
    }

    public function unidadAdministrativa(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class);
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
