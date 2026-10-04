<?php

namespace App\Models\Disciplinario;

use App\Enums\TipoFalta;
use App\Enums\TipoSancion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SancionDisciplinaria extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sanciones_disciplinarias';

    protected $fillable = [
        'sumario_id',
        'tipo_falta',
        'tipo_sancion',
        'porcentaje_multa',
        'dias_suspension',
        'fecha_efectiva',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tipo_falta'       => TipoFalta::class,
            'tipo_sancion'     => TipoSancion::class,
            'porcentaje_multa' => 'decimal:2',
            'dias_suspension'  => 'integer',
            'fecha_efectiva'   => 'date',
        ];
    }

    public function sumario(): BelongsTo
    {
        return $this->belongsTo(Sumario::class, 'sumario_id');
    }
}
