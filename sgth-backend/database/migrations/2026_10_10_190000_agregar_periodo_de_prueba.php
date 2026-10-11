<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El período de prueba y el nombramiento definitivo (LOSEP Art. 17 b;
 * Reglamento 224–227; diseño de Acciones de Personal, 4.2, 8.1 y 9.2; TH N3;
 * fase 3.2).
 *
 * - El supuesto del provisional, en el vínculo y en el ingreso que lo propone.
 *   Los provisionales ya registrados se quedan sin él: no se sabe cuál fue.
 * - El acta final del concurso, en el ingreso.
 * - Tipo y clase `nombramiento_definitivo`, que convierte el provisional de
 *   prueba en permanente sin cerrar el vínculo.
 */
return new class extends Migration
{
    private const SUPUESTOS = ['suspendido_o_destituido', 'puesto_vacante', 'nivel_jerarquico_superior', 'de_prueba'];

    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion', 'reintegro', 'restitucion',
    ];

    public function up(): void
    {
        $supuestos = implode(', ', array_map(fn ($v) => "'{$v}'", self::SUPUESTOS));

        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->string('supuesto_provisional', 40)->nullable()->after('tipo_nombramiento');
        });
        DB::statement("ALTER TABLE contratos_servidor ADD CONSTRAINT contratos_servidor_supuesto_provisional_check
            CHECK (supuesto_provisional IS NULL OR supuesto_provisional IN ({$supuestos}))");

        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('supuesto_provisional_propuesto', 40)->nullable()->after('tipo_nombramiento_propuesto');
            $table->string('acta_concurso_numero', 100)->nullable()->after('informe_uath_numero');
            $table->date('acta_concurso_fecha')->nullable()->after('acta_concurso_numero');
        });
        DB::statement("ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_supuesto_provisional_check
            CHECK (supuesto_provisional_propuesto IS NULL OR supuesto_provisional_propuesto IN ({$supuestos}))");

        $this->restricciones([...self::TIPOS, 'nombramiento_definitivo'], [...self::CLASES, 'nombramiento_definitivo']);
    }

    public function down(): void
    {
        DB::table('movimientos_personal')->where('tipo_movimiento', 'nombramiento_definitivo')->delete();
        $this->restricciones(self::TIPOS, self::CLASES);

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_supuesto_provisional_check');
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn(['supuesto_provisional_propuesto', 'acta_concurso_numero', 'acta_concurso_fecha']);
        });

        DB::statement('ALTER TABLE contratos_servidor DROP CONSTRAINT IF EXISTS contratos_servidor_supuesto_provisional_check');
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropColumn('supuesto_provisional');
        });
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $clases
     */
    private function restricciones(array $tipos, array $clases): void
    {
        $lista = fn (array $v) => implode(', ', array_map(fn (string $x) => "'{$x}'", $v));

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_tipo_movimiento_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_tipo_movimiento_check '
            ."CHECK (tipo_movimiento IN ({$lista($tipos)}))");

        DB::statement('ALTER TABLE movimientos_personal DROP CONSTRAINT IF EXISTS movimientos_personal_clase_check');
        DB::statement('ALTER TABLE movimientos_personal ADD CONSTRAINT movimientos_personal_clase_check '
            ."CHECK (clase IS NULL OR clase IN ({$lista($clases)}))");
    }
};
