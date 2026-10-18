<?php

namespace App\Services\Comunicados;

use App\Contracts\Comunicados\ArchivosComunicadoServiceInterface;
use App\Exceptions\ReglaNegocioException;
use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoAdjunto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * La portada y los PDF de una publicación (docs/diseno-noticias-comunicados.md,
 * regla 6).
 *
 * Van al disco privado y se sirven por controlador, que decide quién los ve:
 * nunca por una URL pública, que es lo que dejó a LOTAIP publicando sin sesión
 * (docs/scheduler.md). Solo se cambian en un borrador: lo publicado está
 * congelado, y un PDF que alguien ya confirmó haber leído no se reemplaza.
 */
final class ArchivosComunicadoService implements ArchivosComunicadoServiceInterface
{
    public const DISCO = 'local';

    public const MAXIMO_ADJUNTOS = 5;

    public function subirPortada(Comunicado $comunicado, UploadedFile $archivo): Comunicado
    {
        $disco = Storage::disk(self::DISCO);
        $ruta = $this->carpeta($comunicado->id).'/portada-'.Str::uuid().'.'.$this->extensionDeImagen($archivo);
        $disco->put($ruta, (string) file_get_contents($archivo->getRealPath()));

        try {
            $anterior = DB::transaction(function () use ($comunicado, $ruta) {
                $bloqueado = $this->bloquearBorrador($comunicado);
                $anterior = $bloqueado->portada_path;
                $bloqueado->update(['portada_path' => $ruta]);

                return $anterior;
            });
        } catch (Throwable $e) {
            $disco->delete($ruta);

            throw $e;
        }

        if ($anterior) {
            $disco->delete($anterior);
        }

        return $comunicado->refresh();
    }

    public function quitarPortada(Comunicado $comunicado): Comunicado
    {
        $anterior = DB::transaction(function () use ($comunicado) {
            $bloqueado = $this->bloquearBorrador($comunicado);
            $anterior = $bloqueado->portada_path;
            $bloqueado->update(['portada_path' => null]);

            return $anterior;
        });

        if ($anterior) {
            Storage::disk(self::DISCO)->delete($anterior);
        }

        return $comunicado->refresh();
    }

    public function agregarAdjunto(Comunicado $comunicado, UploadedFile $archivo): ComunicadoAdjunto
    {
        $disco = Storage::disk(self::DISCO);
        $ruta = $this->carpeta($comunicado->id).'/adjuntos/'.Str::uuid().'.pdf';
        $disco->put($ruta, (string) file_get_contents($archivo->getRealPath()));

        try {
            return DB::transaction(function () use ($comunicado, $archivo, $ruta) {
                $bloqueado = $this->bloquearBorrador($comunicado);
                $cuantos = $bloqueado->adjuntos()->count();

                if ($cuantos >= self::MAXIMO_ADJUNTOS) {
                    throw new ReglaNegocioException(
                        'Una publicación lleva hasta '.self::MAXIMO_ADJUNTOS.' documentos adjuntos: quite uno antes de agregar otro.'
                    );
                }

                return $bloqueado->adjuntos()->create([
                    'nombre_original' => $this->nombreDelPdf($archivo->getClientOriginalName()),
                    'path'            => $ruta,
                    'tamano'          => (int) $archivo->getSize(),
                    'orden'           => ((int) $bloqueado->adjuntos()->max('orden')) + 1,
                ]);
            });
        } catch (Throwable $e) {
            $disco->delete($ruta);

            throw $e;
        }
    }

    public function quitarAdjunto(Comunicado $comunicado, ComunicadoAdjunto $adjunto): void
    {
        DB::transaction(function () use ($comunicado, $adjunto) {
            $this->bloquearBorrador($comunicado);
            $adjunto->delete();
        });

        Storage::disk(self::DISCO)->delete($adjunto->path);
    }

    public function rutaPortada(Comunicado $comunicado): ?string
    {
        return $comunicado->portada_path ? $this->rutaSiExiste($comunicado->portada_path) : null;
    }

    public function rutaAdjunto(ComunicadoAdjunto $adjunto): ?string
    {
        return $this->rutaSiExiste($adjunto->path);
    }

    public function copiar(Comunicado $origen, Comunicado $destino): void
    {
        $disco = Storage::disk(self::DISCO);

        if ($origen->portada_path && $disco->exists($origen->portada_path)) {
            $extension = pathinfo($origen->portada_path, PATHINFO_EXTENSION);
            $ruta = $this->carpeta($destino->id).'/portada-'.Str::uuid().'.'.$extension;
            $disco->copy($origen->portada_path, $ruta);
            $destino->update(['portada_path' => $ruta]);
        }

        foreach ($origen->adjuntos as $adjunto) {
            if (! $disco->exists($adjunto->path)) {
                continue;
            }

            $ruta = $this->carpeta($destino->id).'/adjuntos/'.Str::uuid().'.pdf';
            $disco->copy($adjunto->path, $ruta);
            $destino->adjuntos()->create([
                'nombre_original' => $adjunto->nombre_original,
                'path'            => $ruta,
                'tamano'          => $adjunto->tamano,
                'orden'           => $adjunto->orden,
            ]);
        }
    }

    public function eliminarTodo(int $comunicadoId): void
    {
        Storage::disk(self::DISCO)->deleteDirectory($this->carpeta($comunicadoId));
    }

    private function carpeta(int $comunicadoId): string
    {
        return "comunicados/{$comunicadoId}";
    }

    /** Lo bloquea mientras dura el cambio y exige que siga en borrador. */
    private function bloquearBorrador(Comunicado $comunicado): Comunicado
    {
        $bloqueado = Comunicado::whereKey($comunicado->id)->lockForUpdate()->firstOrFail();

        if (! $bloqueado->esBorrador()) {
            throw new ReglaNegocioException(
                'Los archivos solo se cambian en un borrador. Para corregir lo publicado, retírelo y duplíquelo.'
            );
        }

        return $bloqueado;
    }

    /** La extensión según el contenido, no según el nombre que trae el archivo. */
    private function extensionDeImagen(UploadedFile $archivo): string
    {
        return match ($archivo->getMimeType()) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }

    /** El nombre con que se descarga: sin carpetas ni caracteres de control, y en .pdf. */
    private function nombreDelPdf(string $original): string
    {
        $nombre = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/u', '', basename($original)) ?? '';
        $nombre = trim(Str::limit(pathinfo($nombre, PATHINFO_FILENAME), 180, ''));

        return ($nombre === '' ? 'documento' : $nombre).'.pdf';
    }

    private function rutaSiExiste(string $ruta): ?string
    {
        $disco = Storage::disk(self::DISCO);

        return $disco->exists($ruta) ? $disco->path($ruta) : null;
    }
}
