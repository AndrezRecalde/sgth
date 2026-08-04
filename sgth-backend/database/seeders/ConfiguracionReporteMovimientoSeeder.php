<?php

namespace Database\Seeders;

use App\Enums\TipoMovimientoPersonal;
use App\Models\Reporte\ConfiguracionReporteMovimiento;
use Illuminate\Database\Seeder;

class ConfiguracionReporteMovimientoSeeder extends Seeder
{
    /**
     * Mapeo inicial, NO definitivo — pendiente de confirmar con la UATH
     * del GAD contra el formato exacto que pide SIITH/SUT. Solo se marcan
     * reportable_siith=true los 3 tipos que mapean sin ambigüedad al
     * listado de la norma (ingresos, traslados, cambios administrativos).
     * "Ascensos" quedó SIN mapeo (2026-07-23): el tipo_movimiento 'ascenso'
     * se retiró del catálogo por no existir como acción de personal en la
     * operación real del GAD — la categoría de la norma queda descubierta
     * hasta que se defina cómo se registrará esa acción de personal.
     * Desde la taxonomía de dos niveles (2026-07-27) sí existen 'traspaso' y
     * 'cesacion_funciones' como tipo_movimiento propio, así que la categoría
     * "cesación de funciones" de la norma ya tiene dónde mapear — pero se
     * deja SIN marcar reportable hasta que la UATH lo confirme contra el
     * formato exacto, igual que el resto. "Reingreso/reintegro" sigue sin
     * equivalente. reportable_sut queda enteramente en false: sin lista
     * confirmada para Código de Trabajo todavía.
     */
    public function run(): void
    {
        $reportablesSiith = [
            TipoMovimientoPersonal::INGRESO,
            TipoMovimientoPersonal::TRASLADO,
            TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO,
        ];

        foreach (TipoMovimientoPersonal::cases() as $tipo) {
            $descripcion = match ($tipo) {
                TipoMovimientoPersonal::CESACION_FUNCIONES =>
                    'Mapea a "cesación de funciones" de la norma SIITH (renuncia, destitución, '
                        .'jubilación, incapacidad, contrato finalizado y visto bueno) — confirmar '
                        .'con UATH antes de marcarlo reportable.',
                TipoMovimientoPersonal::EGRESO =>
                    'Tipo genérico anterior a la taxonomía de dos niveles; las bajas nuevas se '
                        .'registran como "cesacion_funciones". Se conserva por el histórico.',
                TipoMovimientoPersonal::TRASPASO =>
                    'Hoy es también subtipo de "cambio administrativo" — confirmar con UATH cuál '
                        .'de los dos reporta SIITH para no duplicar.',
                TipoMovimientoPersonal::REGIMEN_DISCIPLINARIO =>
                    'Sanción disciplinaria (LOSEP). La terminación por visto bueno de un obrero '
                        .'se registra como cesación de funciones, no aquí.',
                default => null,
            };

            ConfiguracionReporteMovimiento::updateOrCreate(
                ['tipo_movimiento' => $tipo->value],
                [
                    'reportable_siith' => in_array($tipo, $reportablesSiith, true),
                    'reportable_sut'   => false,
                    'descripcion'      => $descripcion,
                ]
            );
        }
    }
}
