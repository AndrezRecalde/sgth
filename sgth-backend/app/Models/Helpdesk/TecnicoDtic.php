<?php

namespace App\Models\Helpdesk;

use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un técnico de la mesa de ayuda: su usuario, la subunidad de TIC donde
 * atiende (D6) y su nivel (1 mesa, 2 especialista, 3 enlace con el proveedor).
 */
class TecnicoDtic extends Model
{
    use SoftDeletes;

    protected $table = 'tecnicos_dtic';

    protected $fillable = ['user_id', 'unidad_tic_id', 'nivel', 'estado'];

    protected function casts(): array
    {
        return ['nivel' => 'integer', 'estado' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unidadTic(): BelongsTo
    {
        return $this->belongsTo(UnidadAdministrativa::class, 'unidad_tic_id');
    }
}
