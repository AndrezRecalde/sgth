<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los firmantes de una Acción de Personal pasan a derivarse del organigrama en
 * vez de una designación manual: el jefe vigente de la unidad de Talento
 * Humano y el de la máxima autoridad.
 *
 * Para que esa derivación sea determinista hacen falta dos cosas:
 *
 * 1. Anclajes explícitos. Buscar la unidad por nombre no sirve — hoy hay dos
 *    unidades que dicen "Talento Humano" y dos raíces de nivel 1. Un booleano
 *    marcado a mano una sola vez elimina la ambigüedad.
 *
 * 2. Un solo jefe por unidad. 'es_jefe' no tenía ninguna restricción: dos
 *    puestos jefe en la misma unidad harían que la firma dependiera del orden
 *    de la consulta. Y como un puesto tiene plazas, el jefe debe tener una
 *    sola: con dos plazas habría dos jefes simultáneos ocupando el mismo
 *    puesto. La ausencia temporal del titular se resuelve por subrogación o
 *    encargo, que ya están modelados.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->assertSinViolaciones();

        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->boolean('es_unidad_talento_humano')->default(false)->after('estado');
            $table->boolean('es_maxima_autoridad')->default(false)->after('es_unidad_talento_humano');
        });

        // A lo sumo una unidad de cada tipo. El índice parcial solo cubre las
        // filas con el flag en true, así que las demás no compiten.
        DB::statement('
            CREATE UNIQUE INDEX uq_unidad_talento_humano
            ON unidades_administrativas ((es_unidad_talento_humano))
            WHERE es_unidad_talento_humano AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX uq_unidad_maxima_autoridad
            ON unidades_administrativas ((es_maxima_autoridad))
            WHERE es_maxima_autoridad AND deleted_at IS NULL
        ');

        DB::statement('
            CREATE UNIQUE INDEX uq_puesto_jefe_por_unidad
            ON puestos (unidad_administrativa_id)
            WHERE es_jefe AND deleted_at IS NULL
        ');

        DB::statement('
            ALTER TABLE puestos
            ADD CONSTRAINT puesto_jefe_una_sola_plaza_check
            CHECK (NOT es_jefe OR plazas = 1)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE puestos DROP CONSTRAINT IF EXISTS puesto_jefe_una_sola_plaza_check');
        DB::statement('DROP INDEX IF EXISTS uq_puesto_jefe_por_unidad');
        DB::statement('DROP INDEX IF EXISTS uq_unidad_maxima_autoridad');
        DB::statement('DROP INDEX IF EXISTS uq_unidad_talento_humano');

        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->dropColumn(['es_unidad_talento_humano', 'es_maxima_autoridad']);
        });
    }

    /**
     * Falla con un mensaje accionable en vez de dejar que reviente el índice:
     * si hay datos que violan las reglas nuevas, hay que corregirlos a mano
     * porque decidir cuál puesto es "el jefe" no le corresponde a una
     * migración.
     */
    private function assertSinViolaciones(): void
    {
        $duplicados = DB::table('puestos')
            ->where('es_jefe', true)
            ->whereNull('deleted_at')
            ->select('unidad_administrativa_id', DB::raw('count(*) as total'))
            ->groupBy('unidad_administrativa_id')
            ->having(DB::raw('count(*)'), '>', 1)
            ->pluck('total', 'unidad_administrativa_id');

        if ($duplicados->isNotEmpty()) {
            throw new RuntimeException(
                'Hay unidades con más de un puesto marcado como jefe: '
                    .$duplicados->map(fn ($t, $u) => "unidad {$u} ({$t} puestos)")->join(', ')
                    .'. Deje un solo puesto jefe por unidad antes de aplicar esta migración.'
            );
        }

        $conVariasPlazas = DB::table('puestos')
            ->where('es_jefe', true)
            ->where('plazas', '>', 1)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($conVariasPlazas->isNotEmpty()) {
            throw new RuntimeException(
                'Hay puestos jefe con más de una plaza (ids: '.$conVariasPlazas->join(', ').'). '
                    .'Un puesto de jefatura debe tener una sola plaza; use subrogación o encargo '
                    .'para cubrir ausencias.'
            );
        }
    }
};
