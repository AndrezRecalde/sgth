<?php

namespace App\Console\Commands;

use App\Contracts\Disciplinario\DisciplinarioServiceInterface;
use Illuminate\Console\Command;

class ControlPlazosSumarioCommand extends Command
{
    protected $signature = 'sgth:disciplinario:control-plazos';

    protected $description = 'Alerta sobre sumarios administrativos que excedieron un plazo procesal de la LOSEP.';

    /** Cómo se llama cada plazo en la salida, para no imprimir la clave interna. */
    private const ETIQUETA_PLAZO = [
        'notificacion' => 'Notificación al sumariado',
        'informe'      => 'Informe del instructor',
        'resolucion'   => 'Resolución',
    ];

    public function handle(DisciplinarioServiceInterface $disciplinarioService): int
    {
        $this->info('Controlando plazos procesales de los sumarios...');

        $alertas = $disciplinarioService->controlarPlazosLegales();

        if ($alertas === []) {
            $this->info('Sin sumarios fuera de plazo.');

            return self::SUCCESS;
        }

        $this->warn(count($alertas).' sumario(s) fuera de plazo:');
        $this->table(
            ['Sumario', 'Servidor', 'Plazo', 'Fecha límite', 'Días vencido', 'Riesgo de caducidad'],
            array_map(fn (array $a) => [
                $a['sumario_id'],
                $a['servidor_id'],
                self::ETIQUETA_PLAZO[$a['plazo']] ?? $a['plazo'],
                $a['fecha_limite'],
                $a['dias_vencido'],
                $a['grave'] ? 'Sí' : 'No',
            ], $alertas)
        );

        return self::SUCCESS;
    }
}
