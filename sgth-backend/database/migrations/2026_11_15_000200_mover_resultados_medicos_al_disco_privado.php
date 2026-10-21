<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Saca del disco público los resultados médicos que ya se subieron.
 *
 * Se guardaban en `storage/app/public/resultados-medicos` y se servían en
 * `/storage/resultados-medicos/…` sin sesión: bastaba la dirección para leer
 * un laboratorio o una radiografía. Los nuevos ya van al disco privado; esto
 * mueve los anteriores, con la misma ruta relativa, para que la columna
 * `archivo` siga valiendo sin tocarla.
 *
 * Se mueve la carpeta entera, no solo lo que nombra la tabla: también los
 * archivos que quedaron sin fila al borrar, que eran igual de legibles.
 */
return new class extends Migration
{
    private const CARPETA = 'resultados-medicos';

    public function up(): void
    {
        $this->mover(Storage::disk('public'), Storage::disk('local'));
    }

    public function down(): void
    {
        $this->mover(Storage::disk('local'), Storage::disk('public'));
    }

    private function mover(
        \Illuminate\Contracts\Filesystem\Filesystem $origen,
        \Illuminate\Contracts\Filesystem\Filesystem $destino
    ): void {
        foreach ($origen->allFiles(self::CARPETA) as $ruta) {
            // Si ya está en el destino (una ejecución anterior a medias), se
            // conserva el que hay y solo se retira el del origen.
            if (! $destino->exists($ruta)) {
                $destino->writeStream($ruta, $origen->readStream($ruta));
            }

            $origen->delete($ruta);
        }
    }
};
