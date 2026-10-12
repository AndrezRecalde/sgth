<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El reporte SIITH/SUT por clase (diseño de Acciones de Personal, 9.12; fase
 * 7.5).
 *
 * - Se configura por clase y no por tipo: hasta aquí marcaba tres tipos de la
 *   taxonomía antigua —ingreso, traslado y cambio administrativo— y dejaba
 *   fuera la cesación, la subrogación, las licencias, las sanciones y los
 *   incrementos. La tabla se reutiliza con la clase como llave: las filas por
 *   tipo se reemplazan. El portal lo decide el instrumento del acto: la acción
 *   de personal, el registro del contrato y el acto de autoridad van al SIITH;
 *   la hoja del obrero, al SUT; el contrato civil, a ninguno.
 * - `transcripciones_reporte`: qué se transcribió ya al portal del Ministerio,
 *   para que un acto registrado tarde no se pierda entre meses, y para avisar
 *   del que se anula después de transcribirse.
 *
 * Las marcas iniciales son una propuesta: Talento Humano las coteja con el
 * formato del Ministerio y las cambia desde la pantalla.
 */
return new class extends Migration
{
    /** clase => [SIITH, SUT] */
    private const MARCAS = [
        'ingreso'                   => [true, true],
        'traslado'                  => [true, false],
        'intercambio_voluntario'    => [true, false],
        'comision_con_remuneracion' => [true, false],
        'comision_sin_remuneracion' => [true, false],
        'licencia_sin_remuneracion' => [true, false],
        'subrogacion'               => [true, false],
        'encargo'                   => [true, false],
        'incremento_remuneracion'   => [false, false],
        'cambio_ocupacion'          => [false, false],
        'sancion'                   => [true, false],
        'cesacion'                  => [true, true],
        'reintegro'                 => [true, false],
        'restitucion'               => [true, false],
        'nombramiento_definitivo'   => [true, false],
        'ascenso'                   => [true, false],
        'inicio_periodo'            => [true, false],
        'licencia_consejo'          => [true, false],
        'asuncion_definitiva'       => [true, false],
        'adenda'                    => [false, false],
        'cambio_lugar'              => [false, false],
        'traspaso_puesto'           => [true, false],
        'revision_clasificacion'    => [true, false],
    ];

    public function up(): void
    {
        Schema::table('configuracion_reporte_movimiento', function (Blueprint $table) {
            $table->string('tipo_movimiento', 50)->nullable()->change();
            $table->string('clase', 40)->nullable()->unique()->after('id');
            $table->foreignId('actualizado_por')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('configuracion_reporte_movimiento')->whereNull('clase')->delete();

        foreach (self::MARCAS as $clase => [$siith, $sut]) {
            DB::table('configuracion_reporte_movimiento')->insert([
                'clase'            => $clase,
                'reportable_siith' => $siith,
                'reportable_sut'   => $sut,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        Schema::create('transcripciones_reporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movimiento_id')->constrained('movimientos_personal')->cascadeOnDelete();
            $table->string('portal', 5);
            $table->foreignId('transcrito_por')->constrained('users');
            $table->timestamp('transcrito_en');
            // El acto se anuló después de transcribirse: también se anula en el portal.
            $table->foreignId('anulacion_transcrita_por')->nullable()->constrained('users');
            $table->timestamp('anulacion_transcrita_en')->nullable();
            $table->timestamps();

            $table->unique(['movimiento_id', 'portal']);
        });

        DB::statement("ALTER TABLE transcripciones_reporte ADD CONSTRAINT transcripciones_reporte_portal_check CHECK (portal IN ('siith', 'sut'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('transcripciones_reporte');

        DB::table('configuracion_reporte_movimiento')->whereNotNull('clase')->delete();

        Schema::table('configuracion_reporte_movimiento', function (Blueprint $table) {
            $table->dropConstrainedForeignId('actualizado_por');
            $table->dropUnique(['clase']);
            $table->dropColumn('clase');
        });
    }
};
