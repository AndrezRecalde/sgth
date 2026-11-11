<?php

namespace App\Console\Commands;

use App\Models\Estructura\FichaManualPuestos;
use App\Services\Estructura\Manual\ImportacionDelManualService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Carga las fichas del Manual de Puestos 2018-2019 en la tabla de staging,
 * para que Talento Humano las revise y las empareje con su puesto
 * (docs/diseno-manual-de-puestos.md, P4).
 *
 * El JSON lo genera `scripts/manual-puestos/extraer_fichas.py` y se versiona:
 * en el servidor basta con este comando. Se puede correr otra vez: actualiza
 * cada ficha por su archivo. No toca ningún puesto.
 */
class ImportarManualDePuestosCommand extends Command
{
    protected $signature = 'sgth:manual:importar
        {archivo? : El JSON del extractor; por omisión, database/data/manual-de-puestos-2018-2019.json}
        {--simular : Muestra qué se cargaría, sin guardar nada}
        {--avisos : Lista los avisos de cada ficha}';

    protected $description = 'Carga las fichas del Manual de Puestos 2018-2019 para revisarlas.';

    public function handle(ImportacionDelManualService $importacion): int
    {
        $ruta = $this->argument('archivo') ?: database_path(ImportacionDelManualService::ARCHIVO);

        if (! is_file($ruta)) {
            $this->error("No existe el archivo {$ruta}.");

            return self::FAILURE;
        }

        $fichas = json_decode((string) file_get_contents($ruta), true);
        if (! is_array($fichas) || ! array_is_list($fichas)) {
            $this->error('El archivo no es una lista de fichas en JSON.');

            return self::FAILURE;
        }

        try {
            $resumen = $importacion->importar($fichas, (bool) $this->option('simular'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $porcentaje = fn (int $n) => $resumen['competencias'] > 0
            ? number_format(100 * $n / $resumen['competencias'], 1, ',', '') . ' %'
            : '—';

        $this->table(['', 'Cantidad'], [
            ['Fichas', $resumen['fichas']],
            ['  nuevas', $resumen['nuevas']],
            ['  actualizadas', $resumen['actualizadas']],
            ['  con avisos', $resumen['con_avisos']],
            ['Competencias en las fichas', $resumen['competencias']],
            ['  con el mismo nombre que el diccionario', "{$resumen['exactas']} ({$porcentaje($resumen['exactas'])})"],
            ['  por parecido: erratas o el nombre en otra celda', "{$resumen['aproximadas']} ({$porcentaje($resumen['aproximadas'])})"],
            ['  repetidas en la misma ficha: se descartan', "{$resumen['repetidas']} ({$porcentaje($resumen['repetidas'])})"],
            ['  sin reconocer: se descartan', "{$resumen['sin_reconocer']} ({$porcentaje($resumen['sin_reconocer'])})"],
            ['  con el número corregido', $resumen['renumeradas']],
        ]);

        if ($this->option('avisos')) {
            $this->listarAvisos($fichas);
        }

        if ($this->option('simular')) {
            $this->line('Simulación: no se guardó nada.');
        } else {
            $this->info('Las fichas quedaron para revisarlas y emparejarlas con su puesto. Ningún puesto cambió.');
        }

        return self::SUCCESS;
    }

    /** @param list<array<string, mixed>> $fichas */
    private function listarAvisos(array $fichas): void
    {
        $avisos = FichaManualPuestos::query()
            ->whereIn('archivo', array_column($fichas, 'archivo'))
            ->orderBy('archivo')
            ->get(['archivo', 'avisos']);

        foreach ($avisos as $ficha) {
            if ($ficha->avisos === []) {
                continue;
            }
            $this->newLine();
            $this->line("<comment>{$ficha->archivo}</comment>");
            foreach ($ficha->avisos as $aviso) {
                $this->line("  · {$aviso}");
            }
        }
    }
}
