<?php

namespace Database\Seeders;

use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use Illuminate\Database\Seeder;

class PuestoTicSeeder extends Seeder
{
    public function run(): void
    {
        // Garantiza que las dependencias existan (ambas son idempotentes).
        $this->call([
            GrupoOcupacionalSeeder::class,
            CargoTicSeeder::class,
        ]);

        // No se invoca UnidadAdministrativaSeeder aquí: su 'codigo' se
        // genera con rand() en cada corrida y puede chocar con códigos ya
        // asignados en una base ya sembrada. Si la unidad de TIC no existe
        // todavía, hay que sembrarla manualmente primero.
        $unidad = UnidadAdministrativa::where(
            'nombre',
            'Gestión de Tecnologías de la Información y Comunicación'
        )->first();

        if (!$unidad) {
            throw new \RuntimeException(
                'No existe la unidad "Gestión de Tecnologías de la Información y '
                . 'Comunicación". Ejecuta primero: php artisan db:seed '
                . '--class=UnidadAdministrativaSeeder'
            );
        }

        $cargo = Cargo::where(
            'nombre',
            'Analista de Tecnologías de la Información y Comunicación'
        )->firstOrFail();

        $grupoOcupacional = GrupoOcupacional::where('grado_codigo', 'SP6')->firstOrFail();

        Puesto::updateOrCreate(
            [
                'unidad_administrativa_id' => $unidad->id,
                'cargo_id' => $cargo->id,
            ],
            [
                'grupo_ocupacional_id' => $grupoOcupacional->id,
                // Puesto ficticio de demostración: sin partida presupuestaria asignada.
                'partida_presupuestaria_id' => null,
                'plazas' => 1,
                'rol_puesto' => 'ejecucion_procesos',
                'nivel_complejidad' => 'alto',
                'regimen_laboral' => 'losep',
                'es_jefe' => false,
                'activo' => true,
            ]
        );
    }
}
