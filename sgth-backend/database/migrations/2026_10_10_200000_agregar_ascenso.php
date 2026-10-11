<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El ascenso (LOSEP Arts. 17 b.5 y 68; diseño de Acciones de Personal, 4.2 y
 * 8.2; TH N2; fase 3.3).
 *
 * - Tipo y clase `ascenso`: el servidor de carrera pasa al puesto que ganó en
 *   concurso, con nombramiento provisional de prueba por seis meses.
 * - El supuesto «ascenso» del vínculo provisional.
 * - El puesto que el ascendido deja queda reservado (`puesto_reservado_id`):
 *   cuenta como ocupado hasta que la prueba termine. El vínculo apunta también
 *   al ascenso (`movimiento_ascenso_id`), que guarda adónde vuelve y con qué
 *   remuneración si no la supera.
 */
return new class extends Migration
{
    private const SUPUESTOS = ['suspendido_o_destituido', 'puesto_vacante', 'nivel_jerarquico_superior', 'de_prueba'];

    private const TIPOS = [
        'traslado', 'subrogacion', 'comision_servicios', 'ingreso', 'cambio_denominacion',
        'prestacion_servicios', 'cambio_administrativo', 'comision_sin_remuneracion',
        'licencia_sin_remuneracion', 'incremento_remuneracion', 'traspaso', 'destitucion',
        'cesacion_funciones', 'regimen_disciplinario', 'reintegro', 'restitucion', 'nombramiento_definitivo',
    ];

    private const CLASES = [
        'ingreso', 'traslado', 'intercambio_voluntario', 'comision_con_remuneracion',
        'comision_sin_remuneracion', 'licencia_sin_remuneracion', 'subrogacion', 'encargo',
        'incremento_remuneracion', 'cambio_ocupacion', 'sancion', 'cesacion', 'reintegro', 'restitucion',
        'nombramiento_definitivo',
    ];

    public function up(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->foreignId('puesto_reservado_id')->nullable()->after('puesto_id')
                ->constrained('puestos')->nullOnDelete();
            $table->foreignId('movimiento_ascenso_id')->nullable()->after('movimiento_origen_id')
                ->constrained('movimientos_personal')->nullOnDelete();
        });

        $this->supuestos([...self::SUPUESTOS, 'ascenso']);
        $this->restricciones([...self::TIPOS, 'ascenso'], [...self::CLASES, 'ascenso']);
    }

    public function down(): void
    {
        // El ascendido en prueba se queda provisional y sin supuesto: el ascenso
        // que lo explicaba deja de existir.
        DB::table('contratos_servidor')->where('supuesto_provisional', 'ascenso')->update(['supuesto_provisional' => null]);
        DB::table('movimientos_personal')->where('tipo_movimiento', 'ascenso')->delete();

        $this->restricciones(self::TIPOS, self::CLASES);
        $this->supuestos(self::SUPUESTOS);

        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_ascenso_id');
            $table->dropConstrainedForeignId('puesto_reservado_id');
        });
    }

    /** @param  list<string>  $supuestos */
    private function supuestos(array $supuestos): void
    {
        $lista = implode(', ', array_map(fn (string $v) => "'{$v}'", $supuestos));

        DB::statement('ALTER TABLE contratos_servidor DROP CONSTRAINT IF EXISTS contratos_servidor_supuesto_provisional_check');
        DB::statement('ALTER TABLE contratos_servidor ADD CONSTRAINT contratos_servidor_supuesto_provisional_check '
            ."CHECK (supuesto_provisional IS NULL OR supuesto_provisional IN ({$lista}))");
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
