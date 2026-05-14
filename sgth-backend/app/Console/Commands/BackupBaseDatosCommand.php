<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

#[Signature('backup:base-datos')]
#[Description('Realiza un backup automático de la base de datos PostgreSQL usando pg_dump')]
class BackupBaseDatosCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $fecha = now()->format('Y-m-d_H-i');
        $nombreArchivo = "sgth_backup_{$fecha}.sql.gz";
        $rutaDirectorio = storage_path('app/backups');
        
        if (!File::exists($rutaDirectorio)) {
            File::makeDirectory($rutaDirectorio, 0755, true);
        }
        
        $rutaArchivo = $rutaDirectorio . '/' . $nombreArchivo;

        $comando = sprintf(
            'PGPASSWORD="%s" pg_dump -h %s -p %s -U %s %s | gzip > %s',
            config('database.connections.pgsql.password'),
            config('database.connections.pgsql.host'),
            config('database.connections.pgsql.port'),
            config('database.connections.pgsql.username'),
            config('database.connections.pgsql.database'),
            $rutaArchivo
        );

        $resultado = null;
        $codigoSalida = null;
        exec($comando, $resultado, $codigoSalida);

        if ($codigoSalida === 0 && File::exists($rutaArchivo)) {
            Log::info("Backup de base de datos generado exitosamente: {$nombreArchivo}");
            $this->info("Backup exitoso: {$nombreArchivo}");
            
            // Eliminar backups antiguos (> 30 días)
            $this->eliminarBackupsAntiguos($rutaDirectorio);
        } else {
            Log::error("Falló la generación del backup de la base de datos. Código de salida: {$codigoSalida}");
            $this->error("Fallo al generar el backup.");
        }
    }

    private function eliminarBackupsAntiguos(string $rutaDirectorio): void
    {
        $archivos = File::files($rutaDirectorio);
        $hace30Dias = now()->subDays(30)->getTimestamp();

        foreach ($archivos as $archivo) {
            if ($archivo->getMTime() < $hace30Dias) {
                File::delete($archivo->getPathname());
                Log::info("Backup antiguo eliminado: " . $archivo->getFilename());
            }
        }
    }
}
