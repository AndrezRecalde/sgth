<?php

namespace App\Contracts\Dispensario;

use App\Services\Dispensario\Reportes\AlcanceReporte;
use App\Services\Dispensario\Reportes\FiltrosReporte;
use App\Services\Dispensario\Reportes\ReporteDispensario;

interface CatalogoReportesDispensarioInterface
{
    /**
     * Los reportes que puede pedir este alcance, con lo que la pantalla
     * necesita para dibujarlos.
     *
     * @return list<array{clave: string, titulo: string, descripcion: string, nominal: bool, filtros: list<string>}>
     */
    public function disponibles(AlcanceReporte $alcance): array;

    /**
     * Las opciones de los filtros: profesionales y unidades. Vacías para quien
     * solo ve lo suyo, que no elige ni lo uno ni lo otro.
     *
     * @return array{profesionales: list<array{id: int, nombre: string}>, unidades: list<array{id: int, nombre: string}>}
     */
    public function opciones(AlcanceReporte $alcance): array;

    /**
     * El reporte, si este alcance lo puede pedir. Si no, 403.
     */
    public function reporte(string $clave, AlcanceReporte $alcance): ReporteDispensario;

    /**
     * @return array{columnas: list<array{clave: string, titulo: string}>, filas: list<array<string, scalar|null>>}
     */
    public function generar(string $clave, FiltrosReporte $filtros, AlcanceReporte $alcance): array;
}
