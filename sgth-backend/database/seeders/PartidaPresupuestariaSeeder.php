<?php
namespace Database\Seeders;

use App\Models\Estructura\PartidaPresupuestaria;
use Illuminate\Database\Seeder;

class PartidaPresupuestariaSeeder extends Seeder
{
    public function run(): void
    {
        $partidas = [
            // ── Grupo 51: Gastos en Personal ─────────────
            ['codigo' => '510101', 'descripcion' => 'Remuneraciones Básicas - Sueldos'],
            ['codigo' => '510102', 'descripcion' => 'Remuneraciones Básicas - Salarios'],
            ['codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas'],
            ['codigo' => '510106', 'descripcion' => 'Salarios Unificados'],
            ['codigo' => '510203', 'descripcion' => 'Decimotercer Sueldo'],
            ['codigo' => '510204', 'descripcion' => 'Decimocuarto Sueldo'],
            ['codigo' => '510510', 'descripcion' => 'Servicios Personales por Contrato'],
            ['codigo' => '510601', 'descripcion' => 'Aporte Patronal'],
            ['codigo' => '510602', 'descripcion' => 'Fondo de Reserva'],
            ['codigo' => '510706', 'descripcion' => 'Beneficios Sociales - Desahucio'],
            ['codigo' => '510707', 'descripcion' => 'Beneficios Sociales - Indemnización'],
            ['codigo' => '510803', 'descripcion' => 'Horas Extraordinarias y Suplementarias'],
            ['codigo' => '510901', 'descripcion' => 'Subrogaciones'],
            ['codigo' => '510902', 'descripcion' => 'Encargos'],
            // ── Grupo 53: Bienes y Servicios (viáticos) ──
            ['codigo' => '530303', 'descripcion' => 'Viáticos y Subsistencias en el Interior',
             'grupo_gasto' => 'Bienes y Servicios'],
            ['codigo' => '530304', 'descripcion' => 'Viáticos y Subsistencias en el Exterior',
             'grupo_gasto' => 'Bienes y Servicios'],
        ];

        foreach ($partidas as $partida) {
            PartidaPresupuestaria::firstOrCreate(
                ['codigo' => $partida['codigo']],
                array_merge($partida, [
                    'grupo_gasto' => $partida['grupo_gasto'] ?? 'Gastos en Personal',
                    'activo'      => true,
                ])
            );
        }
    }
}
