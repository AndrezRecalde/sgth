<?php

namespace App\Models\Seleccion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoPostulante extends Model
{
    protected $table = 'documentos_postulante';

    protected $fillable = [
        'postulante_id', 'tipo',
        'nombre_archivo', 'ruta',
        'extension', 'tamano_bytes',
    ];

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class);
    }
}
