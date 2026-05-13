<?php

namespace App\Contracts\Reporteria;

interface ReporteriaServiceInterface
{
    /**
     * Obtiene los KPIs agrupados en tiempo real con soporte de caché.
     *
     * @return array
     */
    public function obtenerKpisDashboard(): array;

    /**
     * Procesa la consulta para un reporte ad hoc basado en parámetros dinámicos.
     *
     * @param array $configuracion
     * @return array
     */
    public function generarReporteAdHoc(array $configuracion): array;

    /** Reportes Legales Predefinidos */
    public function generarDistributivoSueldos(array $filtros): array;
    public function generarNominaConsolidada(array $filtros): array;
    public function generarPlanillaIess(array $filtros): array;
    public function generarFormulario107(array $filtros): array;
    public function generarInformePac(array $filtros): array;
    public function generarReporteAsistencia(array $filtros): array;
    public function generarReporteViaticos(array $filtros): array;
    public function generarReporteAccidentabilidad(array $filtros): array;
}
