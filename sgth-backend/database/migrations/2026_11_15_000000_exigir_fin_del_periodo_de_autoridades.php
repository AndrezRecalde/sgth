<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El fin del período de las autoridades electas pasa a ser obligatorio
 * (2026-10-21). El «Inicio del período» ya lo pedía desde la fase 4.1;
 * quedaban las autoridades de la carga inicial, que entraron sin fin y nunca
 * se cerraban: al llegar la siguiente habría dos Prefectos.
 *
 * A las que no lo tienen se les fija con el período legal de cuatro años, y
 * queda en la bitácora del vínculo para que Talento Humano lo revise contra la
 * credencial del CNE. Si el inicio cargado es de un período anterior —la
 * reelegida cargada con el primero—, se toma el período de cuatro años que
 * corre hoy: con el primero, el comando diario cerraría a una autoridad en
 * funciones.
 *
 * El CHECK cubre lo que no pasa por la aplicación: cargas por SQL, seeders y
 * migraciones de datos. Es el mismo arreglo que el vencimiento de Servicios
 * Profesionales (2026_07_28_170000).
 */
return new class extends Migration
{
    private const AÑOS_DEL_PERIODO = 4;

    public function up(): void
    {
        $hoy = Carbon::today();

        $sinFin = DB::table('contratos_servidor')
            ->where('tipo_nombramiento', 'eleccion_popular')
            ->whereNull('fecha_fin')
            ->orderBy('id')
            ->get(['id', 'servidor_id', 'fecha_inicio', 'estado']);

        foreach ($sinFin as $contrato) {
            $inicio = Carbon::parse($contrato->fecha_inicio)->startOfDay();
            $periodos = 1;
            $fin = $this->finDe($inicio, $periodos);

            // La que sigue en funciones está en el período que corre hoy.
            while ($contrato->estado === 'vigente' && $fin->lt($hoy)) {
                $fin = $this->finDe($inicio, ++$periodos);
            }

            DB::table('contratos_servidor')->where('id', $contrato->id)->update([
                'fecha_fin'  => $fin->toDateString(),
                'updated_at' => now(),
            ]);

            DB::table('eventos_vinculo')->insert([
                'servidor_id'          => $contrato->servidor_id,
                'contrato_servidor_id' => $contrato->id,
                'tipo'                 => 'fin_del_periodo_fijado',
                'fecha'                => $hoy->toDateString(),
                'descripcion'          => 'El sistema fijó el fin del período en el '.$fin->format('d/m/Y')
                    .', con el período legal de cuatro años'
                    .($periodos > 1
                        ? ' que corre hoy: el inicio cargado, del '.$inicio->format('d/m/Y').', es de un período anterior'
                        : ' desde el '.$inicio->format('d/m/Y'))
                    .'. Revíselo contra la credencial del CNE y corríjalo si no coincide.',
                'registrado_por'       => null,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }

        if ($sinFin->isNotEmpty()) {
            echo "  {$sinFin->count()} autoridad(es) electa(s) recibieron el fin del período legal: revíselo en «Autoridades electas».\n";
        }

        DB::statement("
            ALTER TABLE contratos_servidor
            ADD CONSTRAINT contratos_eleccion_popular_con_fin_del_periodo_check
            CHECK (
                tipo_nombramiento <> 'eleccion_popular'
                OR fecha_fin IS NOT NULL
            )
        ");
    }

    public function down(): void
    {
        // Las fechas fijadas se quedan: son las del período legal, y la
        // bitácora dice de dónde salieron.
        DB::statement('
            ALTER TABLE contratos_servidor
            DROP CONSTRAINT IF EXISTS contratos_eleccion_popular_con_fin_del_periodo_check
        ');
    }

    /** El último día del n-ésimo período de cuatro años contado desde el inicio. */
    private function finDe(Carbon $inicio, int $periodos): Carbon
    {
        return $inicio->copy()->addYearsNoOverflow(self::AÑOS_DEL_PERIODO * $periodos)->subDay();
    }
};
