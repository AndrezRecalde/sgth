<?php

namespace App\Console\Commands;

use App\Services\Dispensario\FusionHistoriasService;
use Illuminate\Console\Command;

/**
 * Junta las historias clínicas de un mismo paciente (hallazgo del 2026-10-23).
 *
 * Primero con --simular, para ver qué pacientes tienen más de una, cuál se
 * queda y qué se muda. Lo que no se puede juntar solo —dos odontogramas— se
 * lista y se deja. Antes de los índices únicos por dueño (la migración
 * 2026_11_22_000000) tiene que quedar sin nada que fusionar.
 */
class FusionarHistoriasClinicasCommand extends Command
{
    protected $signature = 'sgth:historias:fusionar
        {--simular : Muestra qué se juntaría, sin guardar nada}
        {--responsable= : Quién autorizó la fusión; queda en la bitácora}';

    protected $description = 'Junta en una sola las historias clínicas de un mismo paciente (servidor o familiar).';

    private const NOMBRE_TABLA = [
        'alergias_paciente'     => 'alergias',
        'antecedentes_paciente' => 'antecedentes',
        'consultas_medicas'     => 'consultas',
        'odontogramas'          => 'odontograma',
        'ordenes_examen'        => 'órdenes de exámenes',
        'resultados_medicos'    => 'resultados',
        'triajes'               => 'triajes',
    ];

    public function handle(FusionHistoriasService $fusion): int
    {
        $simular     = (bool) $this->option('simular');
        $responsable = trim((string) $this->option('responsable'));

        if (! $simular && $responsable === '') {
            $this->error(
                'Indique quién autorizó la fusión con --responsable="…": queda en la bitácora. '
                .'Para ver qué se juntaría sin guardarlo, use --simular.'
            );

            return self::FAILURE;
        }

        $grupos = $fusion->duplicados();

        if ($grupos->isEmpty()) {
            $this->info('Ningún paciente tiene más de una historia clínica.');

            return self::SUCCESS;
        }

        $this->table(
            ['Paciente', 'Cédula', 'Historias', 'Se queda', 'Se muda', 'Observación'],
            $grupos->map(fn (array $g) => [
                $g['paciente'],
                $g['cedula'] ?? '—',
                $g['historias']->pluck('id')->implode(', '),
                $g['queda']->id,
                collect($g['muda'])->map(fn ($n, $tabla) => "{$n} " . self::NOMBRE_TABLA[$tabla])->implode(', ') ?: 'nada',
                $g['conflicto'] ?? '',
            ])->all(),
        );

        $conflictos = $grupos->whereNotNull('conflicto')->count();

        if ($simular) {
            $this->line('Simulación: no se guardó nada.');

            return self::SUCCESS;
        }

        $grupos->whereNull('conflicto')->each(fn (array $g) => $fusion->fusionar($g, $responsable));

        $this->info('Fusionados: ' . ($grupos->count() - $conflictos) . '.');
        if ($conflictos > 0) {
            $this->warn("Sin fusionar: {$conflictos}. Revíselos a mano y vuelva a correr el comando.");
        }

        return self::SUCCESS;
    }
}
