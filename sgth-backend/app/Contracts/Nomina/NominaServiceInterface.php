<?php

namespace App\Contracts\Nomina;

use App\Models\Nomina\Nomina;

interface NominaServiceInterface
{
    /**
     * Calcula los ingresos, descuentos y totales para un periodo específico,
     * generando la nómina en estado borrador o en_proceso.
     *
     * @param string $periodo Formato YYYY-MM
     * @return Nomina
     */
    public function calcularNomina(string $periodo): Nomina;

    /**
     * Cierra de forma definitiva la nómina, congelando los valores y disparando
     * las integraciones (Handoff ERP) y envíos de correo.
     *
     * @param int $nominaId ID de la nómina a cerrar
     * @param int $userId ID del usuario que ejecuta el cierre
     * @return Nomina
     */
    public function cerrarNomina(int $nominaId, int $userId): Nomina;
}
