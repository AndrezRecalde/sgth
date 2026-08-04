<?php

namespace App\Contracts\Reporte;

use Illuminate\Support\Collection;

interface ReporteSiithSutServiceInterface
{
    /**
     * Lista plana de eventos reportables (registrada/notificada) que
     * coinciden con el portal solicitado, filtrando por tipo_movimiento
     * configurado como reportable y por el régimen jurídico real de cada
     * evento (vía ExpedienteService::lineaDeTiempo()).
     *
     * @param  array{portal: string, servidor_id?: int, desde?: string, hasta?: string}  $filtros
     */
    public function movimientosReportables(array $filtros): Collection;

    /**
     * @return array{periodo: string, portal: string, resumen: array<string,int>, detalle: array}
     */
    public function reporteMensual(int $anio, int $mes, string $portal): array;
}
