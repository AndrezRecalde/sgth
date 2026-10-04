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

    /**
     * La ruta en el disco no sale en el JSON: el archivo se baja por
     * GET …/postulantes/{id}/documentos/{id}, que pasa por la autorización.
     */
    protected $hidden = ['ruta'];

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class);
    }
}
