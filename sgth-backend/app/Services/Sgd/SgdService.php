<?php

namespace App\Services\Sgd;

use App\Contracts\Sgd\SgdServiceInterface;
use App\Models\Sgd\DocumentoInstitucional;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Exceptions\ReglaNegocioException;

final class SgdService implements SgdServiceInterface
{
    public function subirDocumento(array $datos, $archivo, int $userId): DocumentoInstitucional
    {
        if (!$archivo || !$archivo->isValid()) {
            throw new ReglaNegocioException('El archivo proporcionado no es válido o está corrupto.');
        }

        // Generar un nombre seguro y almacenar en el disco privado
        $ruta = $archivo->store('sgd/documentos', 'local');

        return DocumentoInstitucional::create([
            'tramite_id'      => $datos['tramite_id'],
            'nombre'          => $datos['nombre'],
            'ruta_archivo'    => $ruta,
            'tipo_mime'       => $archivo->getMimeType(),
            'es_confidencial' => $datos['es_confidencial'] ?? false,
            'publicar_lotaip' => $datos['publicar_lotaip'] ?? false,
            'created_by'      => $userId,
        ]);
    }

    public function generarUrlFirmada(int $documentoId, int $userId, int $minutosExpiracion = 60): string
    {
        $documento = DocumentoInstitucional::findOrFail($documentoId);

        // Si la URL caduca, devolverá error 403 o 401 automáticamente.
        return URL::temporarySignedRoute(
            'sgd.documentos.descargar',
            now()->addMinutes($minutosExpiracion),
            ['documento' => $documento->id]
        );
    }

    public function publicarLotaip(int $documentoId, int $userId): DocumentoInstitucional
    {
        $documento = DocumentoInstitucional::findOrFail($documentoId);
        
        if ($documento->es_confidencial) {
            throw new ReglaNegocioException('No se puede publicar en LOTAIP un documento marcado como confidencial.');
        }

        $documento->publicar_lotaip = true;
        $documento->updated_by = $userId;
        $documento->save();

        return $documento;
    }
}
