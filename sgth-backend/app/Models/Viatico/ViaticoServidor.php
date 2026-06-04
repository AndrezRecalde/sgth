<?php
namespace App\Models\Viatico;

use App\Models\Expediente\Servidor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViaticoServidor extends Model
{
    protected $table = 'viatico_servidores';

    protected $fillable = [
        'viatico_id',
        'servidor_id',
        'es_titular',
    ];

    protected function casts(): array
    {
        return ['es_titular' => 'boolean'];
    }

    public function viatico(): BelongsTo
    {
        return $this->belongsTo(Viatico::class);
    }

    public function servidor(): BelongsTo
    {
        return $this->belongsTo(Servidor::class);
    }
}
