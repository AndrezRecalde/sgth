<?php

namespace App\Contracts\Comunicados;

use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoAdjunto;
use Illuminate\Http\UploadedFile;

/**
 * Los PDF de una publicación (docs/diseno-noticias-comunicados.md, regla 6).
 * Solo se cambian en un borrador.
 */
interface ArchivosComunicadoServiceInterface
{
    /** Agrega un PDF al final; hasta cinco por publicación. */
    public function agregarAdjunto(Comunicado $comunicado, UploadedFile $archivo): ComunicadoAdjunto;

    public function quitarAdjunto(Comunicado $comunicado, ComunicadoAdjunto $adjunto): void;

    /** La ruta en el disco privado, o null si el archivo falta. */
    public function rutaAdjunto(ComunicadoAdjunto $adjunto): ?string;

    /** Copia los adjuntos a un duplicado. */
    public function copiar(Comunicado $origen, Comunicado $destino): void;

    /** Borra del disco todo lo de una publicación eliminada. */
    public function eliminarTodo(int $comunicadoId): void;
}
