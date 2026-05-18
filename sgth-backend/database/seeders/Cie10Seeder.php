<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Cie10Seeder extends Seeder
{
    public function run(): void
    {
        $rutaCsv = database_path('data/Lista-Tabular-CIE-10-1-1.csv');

        if (!file_exists($rutaCsv)) {
            $this->command->error("No se encontró el archivo CSV en la ruta: {$rutaCsv}");
            return;
        }

        // PRECISIÓN 3 - Encoding del CSV
        $contenido = file_get_contents($rutaCsv);
        $contenido = mb_convert_encoding($contenido, 'UTF-8', 'UTF-8');
        
        // Escribir el contenido temporalmente para leerlo con fgetcsv
        $tempPath = tempnam(sys_get_temp_dir(), 'cie10');
        file_put_contents($tempPath, $contenido);

        $archivo = fopen($tempPath, 'r');
        
        // PRECISIÓN 1 - Seeder con primera fila
        // PRECISIÓN 2 - Separador del CSV
        $separador = ',';
        $filaPrueba = fgetcsv($archivo, 1000, $separador);
        
        if ($filaPrueba !== false) {
            // Si la fila no parece tener al menos 2 columnas, intentamos con punto y coma
            if (count($filaPrueba) < 2) {
                $separador = ';';
                rewind($archivo);
                $filaPrueba = fgetcsv($archivo, 1000, $separador);
            }
            
            if (str_contains(strtolower($filaPrueba[0]), 'digo')) {
                // Ya la saltamos, continuar con el resto
            } else {
                // No hay encabezado, necesitamos procesar esta fila también
                rewind($archivo);
            }
        }

        $lote = [];
        $tamañoLote = 500;
        $now = now();

        while (($fila = fgetcsv($archivo, 1000, $separador)) !== false) {
            if (count($fila) < 2) {
                continue;
            }

            $codigo = trim($fila[0]);
            $descripcion = trim($fila[1]);
            
            if (empty($codigo)) {
                continue;
            }

            $lote[] = [
                'codigo' => $codigo,
                'descripcion' => mb_strtoupper($descripcion, 'UTF-8'),
                'categoria' => substr($codigo, 0, 3),
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($lote) >= $tamañoLote) {
                DB::table('diagnosticos_cie10')->insertOrIgnore($lote);
                $lote = [];
            }
        }

        if (count($lote) > 0) {
            DB::table('diagnosticos_cie10')->insertOrIgnore($lote);
        }

        fclose($archivo);
        unlink($tempPath);

        $this->command->info("Catálogo CIE-10 cargado exitosamente.");
    }
}
