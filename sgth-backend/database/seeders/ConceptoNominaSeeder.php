<?php

namespace Database\Seeders;

use App\Models\Nomina\ConceptoNomina;
use Illuminate\Database\Seeder;

class ConceptoNominaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conceptos = [
            [
                'codigo' => 'SUELDO_BASE',
                'nombre' => 'Sueldo Base / RMU',
                'tipo' => 'ingreso',
                'porcentaje' => null,
            ],
            [
                'codigo' => 'HORAS_EXTRAS',
                'nombre' => 'Horas Suplementarias y Extraordinarias',
                'tipo' => 'ingreso',
                'porcentaje' => null,
            ],
            [
                'codigo' => 'DECIMO_TERCERO',
                'nombre' => 'Décimo Tercer Sueldo Proporcional',
                'tipo' => 'ingreso',
                'porcentaje' => null,
            ],
            [
                'codigo' => 'DECIMO_CUARTO',
                'nombre' => 'Décimo Cuarto Sueldo Proporcional',
                'tipo' => 'ingreso',
                'porcentaje' => null,
            ],
            [
                'codigo' => 'FONDOS_RESERVA',
                'nombre' => 'Fondos de Reserva (8.33%)',
                'tipo' => 'ingreso',
                'porcentaje' => 8.33,
            ],
            [
                'codigo' => 'IESS_PERSONAL',
                'nombre' => 'Aporte Personal IESS (9.45%)',
                'tipo' => 'descuento',
                'porcentaje' => 9.45,
            ],
            [
                'codigo' => 'IESS_PATRONAL',
                'nombre' => 'Aporte Patronal IESS (11.15%)',
                'tipo' => 'aporte', // No descuenta del empleado, es aporte del empleador
                'porcentaje' => 11.15,
            ],
            [
                'codigo' => 'RETENCION_IR',
                'nombre' => 'Retención Impuesto a la Renta',
                'tipo' => 'descuento',
                'porcentaje' => null,
            ],
        ];

        foreach ($conceptos as $concepto) {
            ConceptoNomina::firstOrCreate(
                ['codigo' => $concepto['codigo']],
                [
                    'nombre'     => $concepto['nombre'],
                    'tipo'       => $concepto['tipo'],
                    'porcentaje' => $concepto['porcentaje'],
                    'activo'     => true,
                ]
            );
        }
    }
}
