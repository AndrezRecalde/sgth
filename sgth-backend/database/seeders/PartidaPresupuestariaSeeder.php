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
            // 51.05.12 y 51.05.13, confirmadas por la Dirección Financiera en
            // agosto de 2026. Antes figuraban como 510901 y 510902, códigos
            // inventados para datos de prueba que nadie llegó a usar.
            ['codigo' => '510512', 'descripcion' => 'Subrogaciones'],
            ['codigo' => '510513', 'descripcion' => 'Encargos'],
            // ── Grupo 53: Bienes y Servicios (viáticos) ──
            ['codigo' => '530303', 'descripcion' => 'Viáticos y Subsistencias en el Interior',
             'grupo_gasto' => 'Bienes y Servicios'],
            ['codigo' => '530304', 'descripcion' => 'Viáticos y Subsistencias en el Exterior',
             'grupo_gasto' => 'Bienes y Servicios'],

            // ── Las que respaldan una remuneración, según la Dirección
            //    Financiera del GAD (agosto 2026) ──────────────────────
            //
            // El clasificador separa el mismo concepto en gasto corriente
            // (grupos 51 y 53) y gasto de inversión (71 y 73). Por eso el
            // honorario por contrato civil aparece dos veces: la partida no
            // la decide la modalidad del contrato sino de dónde sale el
            // dinero que lo financia — y eso el sistema no puede deducirlo.
            //
            // Correspondencia confirmada por Financiera:
            //   510105  empleados, nombramiento provisional, libre
            //           nombramiento y remoción, elección popular
            //   510510  contrato ocasional
            //   510512  subrogaciones
            //   510513  encargos
            //   710106  obreros
            //   530606 / 730606  contrato profesional (corriente o inversión)
            ['codigo' => '530606', 'descripcion' => 'Honorarios por Contratos Civiles de Servicios',
             'grupo_gasto' => 'Bienes y Servicios'],
            ['codigo' => '730606', 'descripcion' => 'Honorarios por Contratos Civiles de Servicios',
             'grupo_gasto' => 'Bienes y Servicios para Inversión'],
            ['codigo' => '710106', 'descripcion' => 'Salarios Unificados',
             'grupo_gasto' => 'Gastos en Personal para Inversión'],
        ];

        foreach ($partidas as $partida) {
            $grupoGasto = $partida['grupo_gasto'] ?? 'Gastos en Personal';

            // Disponibilidad presupuestaria verificada solo para las que
            // pagan personal: son las que el guard del Art. 105 LOSEP
            // necesita encontrar "disponibles" en un entorno de desarrollo
            // limpio para que el flujo económico sea probable sin pasos
            // manuales. Viáticos y honorarios quedan sin disponibilidad para
            // seguir probando también la ruta de bloqueo.
            //
            // En producción esto lo fija la Dirección Financiera partida por
            // partida: aquí solo se siembra un punto de partida razonable.
            $pagaPersonal = str_starts_with($grupoGasto, 'Gastos en Personal');

            PartidaPresupuestaria::updateOrCreate(
                ['codigo' => $partida['codigo']],
                array_merge($partida, [
                    'grupo_gasto' => $grupoGasto,
                    'activo'      => true,
                    'disponible'  => $pagaPersonal,
                ])
            );
        }
    }
}
