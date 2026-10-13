<?php

namespace App\Console\Commands;

use App\Exceptions\ReglaNegocioException;
use App\Exports\InformeCargaInventarioExport;
use App\Services\InventarioTi\Carga\CargaInventarioService;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Excel as Formato;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Carga el inventario de equipos desde el Excel de TIC, pestaña «Equipos»
 * (docs/diseno-subsistema-tic.md, sección 7; fase 1.4).
 *
 * Primero con --simular: hace la carga entera, la deshace y deja el informe.
 * TIC lo revisa —nombres que no se encontraron en el expediente, áreas sin
 * equivalencia, contratos de vigencia— y después se corre sin --simular. Se
 * puede volver a correr: los códigos que ya están en el inventario se omiten.
 *
 *   php artisan inventario:cargar-excel "Inventario P2026.xlsm" --simular
 *   php artisan inventario:cargar-excel "Inventario P2026.xlsm" --responsable="Jefe de TIC"
 */
class CargarInventarioExcelCommand extends Command
{
    protected $signature = 'inventario:cargar-excel
        {archivo : El Excel de TIC (.xlsx o .xlsm)}
        {--simular : Hace la carga y la deshace: solo deja el informe}
        {--responsable= : Quién autorizó la carga; queda en la bitácora}
        {--informe= : Dónde guardar el informe (por defecto, en storage/app/private/inventario/cargas)}';

    protected $description = 'Carga el inventario de equipos desde la pestaña «Equipos» del Excel de TIC.';

    public function handle(CargaInventarioService $carga): int
    {
        $simular = (bool) $this->option('simular');
        $responsable = trim((string) $this->option('responsable'));

        if (! $simular && $responsable === '') {
            $this->error('Indique quién autorizó la carga con --responsable="…": queda en la bitácora. Para ver el resultado sin guardar nada, use --simular.');

            return self::FAILURE;
        }

        try {
            $informe = $carga->cargar((string) $this->argument('archivo'), $simular);
        } catch (ReglaNegocioException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $simular) {
            activity('inventario')
                ->withProperties(['responsable' => $responsable, 'archivo' => basename((string) $this->argument('archivo')), ...$informe['resumen']])
                ->log('Carga del inventario desde el Excel de TIC');
        }

        $ruta = $this->guardarInforme($informe, $simular);

        $this->info($simular ? 'Simulación: no se guardó nada en el inventario.' : 'Carga terminada.');
        $this->table(['Concepto', 'Cantidad'], collect($informe['resumen'])
            ->map(fn (int $v, string $k) => [ucfirst(str_replace('_', ' ', $k)), $v])->values()->all());
        $this->line("Informe: {$ruta}");

        return self::SUCCESS;
    }

    private function guardarInforme(array $informe, bool $simular): string
    {
        $ruta = $this->option('informe')
            ?: storage_path('app/private/inventario/cargas/informe-' . now()->format('Ymd-His') . ($simular ? '-simulacion' : '') . '.xlsx');

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }
        file_put_contents($ruta, Excel::raw(new InformeCargaInventarioExport($informe, $simular), Formato::XLSX));

        return $ruta;
    }
}
