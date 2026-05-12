<?php

namespace App\Console\Commands;

use App\Models\Nomina\Nomina;
use App\Models\User;
use App\Jobs\Nomina\ProcesarCierreNominaJob;
use Illuminate\Console\Command;

class CerrarNominaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nomina:cerrar {periodo? : El período de nómina a cerrar (YYYY-MM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierra de forma definitiva la nómina de un período específico y dispara las integraciones ERP.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $periodo = $this->argument('periodo');

        if (!$periodo) {
            $periodo = now()->format('Y-m');
            $this->info("No se especificó período. Utilizando el período actual: {$periodo}");
        }

        // Validación simple de formato YYYY-MM
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
            $this->error('El formato del período debe ser YYYY-MM. (Ej: 2026-05)');
            return Command::FAILURE;
        }

        $nomina = Nomina::where('periodo', $periodo)->first();

        if (!$nomina) {
            $this->error("No se encontró una nómina registrada para el período {$periodo}.");
            return Command::FAILURE;
        }

        if (in_array($nomina->estado->value, ['cerrada', 'pagada', 'contabilizada'])) {
            $this->warn("La nómina del período {$periodo} ya se encuentra en estado: {$nomina->estado->value}. No se puede cerrar nuevamente.");
            return Command::SUCCESS;
        }

        $this->info("Iniciando cierre de la nómina para el período {$periodo}...");

        // Obtenemos o asumimos el ID del usuario administrador o sistema (Ej. ID 1) para la auditoría de consola
        $adminUserId = User::whereHas('roles', fn($q) => $q->where('name', 'admin-uath'))->first()->id ?? 1;

        // Despachar el Job a la cola
        ProcesarCierreNominaJob::dispatch($nomina->id, $adminUserId);

        $this->info("El cierre de la nómina ha sido encolado exitosamente. Los procesos de ERP y correos se ejecutarán en segundo plano.");

        return Command::SUCCESS;
    }
}
