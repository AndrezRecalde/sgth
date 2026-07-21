<?php

namespace App\Services\Sso;

use App\Exceptions\ReglaNegocioException;
use App\Models\Sso\CapacitacionSso;
use App\Models\Sso\CumplimientoNormativa;
use App\Models\Sso\DocumentoSso;
use App\Models\Sso\InspeccionSso;
use App\Models\Sso\ProgramaDrogaSeguimiento;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

final class DocumentoSsoService
{
    /**
     * Allow-list de modelos que pueden recibir adjuntos (Fase 9). El cliente nunca envía el
     * FQCN real: solo esta clave corta, para no exponer la estructura interna ni permitir
     * adjuntar documentos a un modelo arbitrario del sistema.
     */
    private const TIPOS_PERMITIDOS = [
        'cumplimiento_normativa' => CumplimientoNormativa::class,
        'inspeccion_sso' => InspeccionSso::class,
        'capacitacion_sso' => CapacitacionSso::class,
        'programa_drogas_seguimiento' => ProgramaDrogaSeguimiento::class,
    ];

    public static function tiposPermitidos(): array
    {
        return array_keys(self::TIPOS_PERMITIDOS);
    }

    public function subirDocumento(string $tipo, int $documentableId, UploadedFile $archivo, string $nombre, int $userId): DocumentoSso
    {
        $clase = $this->resolverClase($tipo);

        // findOrFail asegura que no se puedan adjuntar documentos a un registro inexistente.
        $documentable = $clase::findOrFail($documentableId);

        if (! $archivo->isValid()) {
            throw new ReglaNegocioException('El archivo proporcionado no es válido o está corrupto.');
        }

        $ruta = $archivo->store('sso/documentos', 'local');

        return $documentable->documentos()->create([
            'nombre' => $nombre,
            'ruta_archivo' => $ruta,
            'tipo_mime' => $archivo->getMimeType(),
            'tamano_bytes' => $archivo->getSize(),
            'subido_por' => $userId,
        ]);
    }

    public function listarDocumentos(string $tipo, int $documentableId): Collection
    {
        $clase = $this->resolverClase($tipo);
        $documentable = $clase::findOrFail($documentableId);

        return $documentable->documentos()->with('subidor')->orderByDesc('created_at')->get();
    }

    public function eliminarDocumento(int $id): void
    {
        DocumentoSso::findOrFail($id)->delete();
    }

    public function generarUrlFirmada(int $documentoId, int $minutosExpiracion = 60): string
    {
        $documento = DocumentoSso::findOrFail($documentoId);

        return URL::temporarySignedRoute(
            'sso.documentos.descargar',
            now()->addMinutes($minutosExpiracion),
            ['documento' => $documento->id]
        );
    }

    public function descargar(int $documentoId): array
    {
        $documento = DocumentoSso::findOrFail($documentoId);

        if (! Storage::disk('local')->exists($documento->ruta_archivo)) {
            throw new ReglaNegocioException('El archivo físico no se encuentra en el servidor.');
        }

        // 'nombre' es la etiqueta libre que escribió el usuario (sin extensión); el archivo
        // descargado debe llevar la extensión real para que el sistema operativo sepa abrirlo.
        return [$documento->ruta_archivo, $this->nombreConExtension($documento), $documento->tipo_mime];
    }

    private function nombreConExtension(DocumentoSso $documento): string
    {
        $extension = pathinfo($documento->ruta_archivo, PATHINFO_EXTENSION);

        if ($extension === '' || str_ends_with(strtolower($documento->nombre), '.' . strtolower($extension))) {
            return $documento->nombre;
        }

        return "{$documento->nombre}.{$extension}";
    }

    private function resolverClase(string $tipo): string
    {
        if (! isset(self::TIPOS_PERMITIDOS[$tipo])) {
            throw new ReglaNegocioException("El tipo de documento \"{$tipo}\" no es válido.");
        }

        return self::TIPOS_PERMITIDOS[$tipo];
    }
}
