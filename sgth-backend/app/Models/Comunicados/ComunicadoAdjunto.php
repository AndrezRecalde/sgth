<?php

namespace App\Models\Comunicados;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un PDF de una publicación: el documento firmado, un formato, un cronograma
 * (docs/diseno-noticias-comunicados.md, regla 6). Vive en el disco privado y
 * se descarga por controlador.
 */
class ComunicadoAdjunto extends Model
{
    protected $table = 'comunicado_adjuntos';

    protected $fillable = ['comunicado_id', 'nombre_original', 'path', 'tamano', 'orden'];

    protected function casts(): array
    {
        return [
            'tamano' => 'integer',
            'orden'  => 'integer',
        ];
    }

    public function comunicado(): BelongsTo
    {
        return $this->belongsTo(Comunicado::class);
    }
}
