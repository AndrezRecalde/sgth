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
            // INGRESOS
            ['codigo' => 'SUELDO_BASE', 'nombre' => 'Sueldo Base / RMU', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'HORAS_EXTRAS_50', 'nombre' => 'Horas Extras al 50% (suplementarias)', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'HORAS_EXTRAS_100', 'nombre' => 'Horas Extras al 100% (extraordinarias)', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'HORAS_NOCTURNAS', 'nombre' => 'Recargo Nocturno', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'SUBROGACION', 'nombre' => 'Diferencia por Subrogación', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'BONO_RESPONSABILIDAD', 'nombre' => 'Bono de Responsabilidad', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'DECIMO_TERCERO', 'nombre' => 'Décimo Tercer Sueldo', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'DECIMO_CUARTO', 'nombre' => 'Décimo Cuarto Sueldo', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'VACACIONES_PAGADAS', 'nombre' => 'Vacaciones Compensadas al Retiro', 'tipo' => 'ingreso', 'porcentaje' => null],
            ['codigo' => 'LIQUIDACION_HABERES', 'nombre' => 'Liquidación de Haberes', 'tipo' => 'ingreso', 'porcentaje' => null],

            // DESCUENTOS
            ['codigo' => 'IESS_PERSONAL', 'nombre' => 'Aporte Personal IESS (porcentaje: 9.45)', 'tipo' => 'descuento', 'porcentaje' => 9.45],
            ['codigo' => 'RETENCION_IR', 'nombre' => 'Retención en la Fuente - Impuesto a la Renta', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'MULTA_INASISTENCIA', 'nombre' => 'Multa por Inasistencia Injustificada', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'MULTA_ATRASO', 'nombre' => 'Descuento por Atraso', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'PRESTAMO_QUIROGRAFARIO', 'nombre' => 'Cuota Préstamo Quirografario IESS', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'PRESTAMO_HIPOTECARIO', 'nombre' => 'Cuota Préstamo Hipotecario IESS', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'PRESTAMO_INSTITUCIONAL', 'nombre' => 'Préstamo Institucional GAD', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'ANTICIPO_SUELDO', 'nombre' => 'Descuento Anticipo de Sueldo', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'SEGURO_VIDA', 'nombre' => 'Seguro de Vida Colectivo', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'PENSION_ALIMENTICIA', 'nombre' => 'Pensión Alimenticia (orden judicial)', 'tipo' => 'descuento', 'porcentaje' => null],
            ['codigo' => 'EMBARGO_JUDICIAL', 'nombre' => 'Embargo Judicial', 'tipo' => 'descuento', 'porcentaje' => null],

            // APORTES INSTITUCIONALES
            ['codigo' => 'IESS_PATRONAL', 'nombre' => 'Aporte Patronal IESS (porcentaje: 11.15)', 'tipo' => 'aporte', 'porcentaje' => 11.15],
            ['codigo' => 'FONDOS_RESERVA', 'nombre' => 'Fondos de Reserva (porcentaje: 8.33)', 'tipo' => 'aporte', 'porcentaje' => 8.33],
            ['codigo' => 'DECIMO_TERCERO_PROV', 'nombre' => 'Provisión Décimo Tercer Sueldo', 'tipo' => 'aporte', 'porcentaje' => null],
            ['codigo' => 'DECIMO_CUARTO_PROV', 'nombre' => 'Provisión Décimo Cuarto Sueldo', 'tipo' => 'aporte', 'porcentaje' => null],
            ['codigo' => 'VACACIONES_PROV', 'nombre' => 'Provisión Vacaciones', 'tipo' => 'aporte', 'porcentaje' => null],
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
