<?php

namespace App\Console\Commands;

use App\Models\Asistencia\FeriadoInstitucional;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class RegistrarFeriadosMovilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sgth:feriados:registrar-moviles {anio : El año para el cual registrar los feriados (Ej: 2027)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registra interactivamente los feriados móviles (Carnaval y Viernes Santo) para un año específico.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $anio = $this->argument('anio');

        $this->info("=== Registro de Feriados Móviles del Ecuador para {$anio} ===");
        $this->warn("Ejecutar este comando en diciembre de cada año para registrar los feriados móviles del año siguiente.");

        // Validar año
        if (!is_numeric($anio) || strlen($anio) !== 4) {
            $this->error("El año proporcionado '{$anio}' no es válido.");
            return 1;
        }

        $carnavalLunes = $this->askFecha("Ingrese la fecha de Carnaval Lunes del {$anio} (YYYY-MM-DD)");
        $carnavalMartes = $this->askFecha("Ingrese la fecha de Carnaval Martes del {$anio} (YYYY-MM-DD)");
        $viernesSanto = $this->askFecha("Ingrese la fecha de Viernes Santo del {$anio} (YYYY-MM-DD)");

        if (!$this->confirm("Se registrarán: \n- Carnaval Lunes: {$carnavalLunes}\n- Carnaval Martes: {$carnavalMartes}\n- Viernes Santo: {$viernesSanto}\n¿Es correcto?", true)) {
            $this->info("Operación cancelada.");
            return 0;
        }

        $feriados = [
            ['fecha' => $carnavalLunes, 'descripcion' => "Carnaval - Lunes {$anio}"],
            ['fecha' => $carnavalMartes, 'descripcion' => "Carnaval - Martes {$anio}"],
            ['fecha' => $viernesSanto, 'descripcion' => "Viernes Santo {$anio}"],
        ];

        foreach ($feriados as $feriado) {
            $registro = FeriadoInstitucional::firstOrCreate(
                [
                    'fecha'    => $feriado['fecha'],
                    'es_movil' => true,
                ],
                [
                    'mes'         => null,
                    'dia'         => null,
                    'descripcion' => $feriado['descripcion'],
                    'es_nacional' => true,
                ]
            );

            if ($registro->wasRecentlyCreated) {
                $this->line("✔ Registrado: {$feriado['descripcion']} ({$feriado['fecha']})");
            } else {
                $this->line("⚠ Ya existía: {$feriado['descripcion']} ({$feriado['fecha']})");
            }
        }

        $this->info("¡Feriados móviles del {$anio} registrados con éxito!");
        return 0;
    }

    private function askFecha(string $pregunta): string
    {
        $fecha = null;
        while (!$fecha) {
            $input = $this->ask($pregunta);
            
            $validator = Validator::make(['fecha' => $input], [
                'fecha' => 'required|date_format:Y-m-d'
            ]);

            if ($validator->fails()) {
                $this->error("Formato inválido. Use YYYY-MM-DD.");
            } else {
                $fecha = $input;
            }
        }
        return $fecha;
    }
}
