<?php

namespace Database\Seeders;

use App\Enums\ClaseAccionPersonal;
use App\Models\Reporte\ConfiguracionReporteMovimiento;
use Illuminate\Database\Seeder;

class ConfiguracionReporteMovimientoSeeder extends Seeder
{
    /**
     * Una fila por clase de acto, con su marca SIITH y SUT (fase 7.5). Las de
     * aquí son la propuesta inicial, pendiente de que Talento Humano la coteje
     * con el formato del Ministerio: al SIITH, todo lo de la LOSEP y de las
     * autoridades; al SUT, el ingreso y la terminación del obrero. Lo que
     * cambia el obrero en su contrato y la adenda del contrato civil, a
     * ninguno.
     *
     * Con `firstOrCreate`: volver a sembrar no pisa lo que Talento Humano
     * cambió en la pantalla.
     */
    public function run(): void
    {
        $alSut = [ClaseAccionPersonal::INGRESO, ClaseAccionPersonal::CESACION];
        $aNinguno = [
            ClaseAccionPersonal::INCREMENTO_REMUNERACION, ClaseAccionPersonal::CAMBIO_OCUPACION,
            ClaseAccionPersonal::CAMBIO_LUGAR, ClaseAccionPersonal::ADENDA,
        ];

        foreach (ClaseAccionPersonal::cases() as $clase) {
            ConfiguracionReporteMovimiento::firstOrCreate(['clase' => $clase->value], [
                'reportable_siith' => ! in_array($clase, $aNinguno, true),
                'reportable_sut'   => in_array($clase, $alSut, true),
            ]);
        }
    }
}
