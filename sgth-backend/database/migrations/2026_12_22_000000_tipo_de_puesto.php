<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El tipo de cada puesto: de plantilla o de contrato
 * (docs/diseno-manual-de-puestos.md, P9).
 *
 * Hasta aquí el contrato decía si ocupaba plaza, pero el puesto no decía si
 * era del distributivo. Un puesto que solo usaban contratos de servicios
 * profesionales contaba sus plazas como vacantes en la Plantilla.
 *
 * - Todos los puestos nacen `plantilla`, que es como se comportan hoy. Cuáles
 *   pasan a `contrato` lo confirma Talento Humano.
 * - Un puesto de contrato no tiene plazas (`plazas` nulo), porque nada de lo
 *   que lo ocupa consume una; y no es jefatura.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sinPlazas = DB::table('puestos')->where(fn ($q) => $q->whereNull('plazas')->orWhere('plazas', '<', 1))
            ->pluck('id');

        if ($sinPlazas->isNotEmpty()) {
            throw new RuntimeException(
                'Hay puestos sin plazas, y un puesto de plantilla tiene al menos una: '.$sinPlazas->implode(', ')
                .'. Corrija sus plazas antes de migrar.'
            );
        }

        DB::statement("ALTER TABLE puestos ADD COLUMN tipo varchar(20) NOT NULL DEFAULT 'plantilla'");
        // El `DEFAULT 1` se queda: lo que crea un puesto sin decir sus plazas
        // sigue creando uno de plantilla con una. El de contrato las pone nulas.
        DB::statement('ALTER TABLE puestos ALTER COLUMN plazas DROP NOT NULL');

        DB::statement("ALTER TABLE puestos ADD CONSTRAINT puesto_tipo_check CHECK (tipo IN ('plantilla', 'contrato'))");
        DB::statement("
            ALTER TABLE puestos ADD CONSTRAINT puesto_plazas_segun_tipo_check CHECK (
                (tipo = 'plantilla' AND plazas IS NOT NULL AND plazas >= 1)
                OR (tipo = 'contrato' AND plazas IS NULL)
            )
        ");
        // `puesto_jefe_una_sola_plaza_check` (NOT es_jefe OR plazas = 1) pasa
        // con `plazas` nulo —en un CHECK, nulo no es falso—: la jefatura de un
        // puesto de contrato necesita su propia regla. Por lo mismo, la de
        // arriba dice `plazas IS NOT NULL`.
        DB::statement("ALTER TABLE puestos ADD CONSTRAINT puesto_contrato_sin_jefatura_check CHECK (tipo = 'plantilla' OR NOT es_jefe)");
        DB::statement('CREATE INDEX puestos_tipo_index ON puestos (tipo)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS puestos_tipo_index');
        DB::statement('ALTER TABLE puestos DROP CONSTRAINT IF EXISTS puesto_contrato_sin_jefatura_check');
        DB::statement('ALTER TABLE puestos DROP CONSTRAINT IF EXISTS puesto_plazas_segun_tipo_check');
        DB::statement('ALTER TABLE puestos DROP CONSTRAINT IF EXISTS puesto_tipo_check');
        // Un puesto de contrato vuelve con una plaza, como nacía todo puesto.
        DB::statement('UPDATE puestos SET plazas = 1 WHERE plazas IS NULL');
        DB::statement('ALTER TABLE puestos ALTER COLUMN plazas SET NOT NULL');
        DB::statement('ALTER TABLE puestos DROP COLUMN tipo');
    }
};
