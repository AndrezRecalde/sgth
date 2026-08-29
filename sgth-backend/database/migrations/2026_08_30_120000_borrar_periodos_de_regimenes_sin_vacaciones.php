<?php

use App\Enums\RegimenLaboral;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Borra los períodos de vacaciones que nunca debieron abrirse.
 *
 * Hasta ahora la generación creaba un período con cero días para todo el
 * mundo, contratos civiles incluidos. Eso dejaba a un servicios profesionales
 * dentro de la pantalla de vacaciones, contándose entre los períodos de la
 * plantilla, con un saldo de cero que alguien podía interpretar como «se le
 * acabaron los días» en vez de «no le corresponden».
 *
 * SOLO se borran los períodos intactos —sin días gozados y sin saldo—. Si un
 * período tiene consumo es porque la persona estuvo bajo otro régimen y
 * efectivamente gozó vacaciones: eso ocurrió, y borrarlo sería reescribir la
 * historia. Esos se quedan y se recalculan a cero conservando lo gozado, que
 * es lo que ya hacía `generarPeriodo()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinVacaciones = RegimenLaboral::valoresSinVacaciones();

        if ($sinVacaciones === []) {
            return;
        }

        $borrados = DB::table('periodos_vacaciones')
            ->whereIn('servidor_id', function ($query) use ($sinVacaciones) {
                $query->select('id')
                    ->from('servidores')
                    ->whereIn('regimen_laboral', $sinVacaciones);
            })
            ->where('dias_utilizados', '<=', 0)
            ->where('dias_saldo', '<=', 0)
            ->delete();

        if ($borrados > 0) {
            echo "  Períodos de vacaciones eliminados por régimen sin derecho: {$borrados}\n";
        }
    }

    public function down(): void
    {
        // No se recrean: eran filas vacías que el sistema ya no genera. Si
        // alguien vuelve a un régimen con vacaciones, su período se crea solo
        // en la siguiente generación.
    }
};
