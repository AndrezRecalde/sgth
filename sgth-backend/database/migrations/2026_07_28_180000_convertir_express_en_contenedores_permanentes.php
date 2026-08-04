<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El reclutamiento express deja de ser "una convocatoria por proceso" y pasa a
 * ser un contenedor permanente por modalidad (confirmado con Talento Humano):
 * existen cuatro fijos —Provisionales, Ocasionales, Servicios Profesionales y
 * Código del Trabajo— y los aspirantes se agregan sueltos dentro de ellos.
 *
 * Consecuencias en el esquema:
 * - El contenedor no tiene un puesto único, así que 'convocatorias.puesto_id'
 *   pasa a nullable y el puesto viaja en cada aspirante.
 * - El aspirante gana 'fecha_inscripcion': el año sale de ahí, no del
 *   contenedor, que no tiene año.
 * - La unicidad (convocatoria, cédula) impedía que alguien fuera contratado en
 *   2026 y otra vez en 2027 dentro de la misma modalidad. Pasa a ser por año.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convocatorias', function (Blueprint $table) {
            // Marca los cuatro contenedores fijos para que no puedan
            // eliminarse ni cerrarse como una convocatoria cualquiera.
            $table->boolean('es_contenedor_permanente')->default(false)->after('tipo_nombramiento_previsto');
        });

        DB::statement('ALTER TABLE convocatorias ALTER COLUMN puesto_id DROP NOT NULL');

        // Un contenedor permanente no tiene puesto; una convocatoria normal sí.
        DB::statement("
            ALTER TABLE convocatorias
            ADD CONSTRAINT convocatorias_puesto_segun_contenedor_check
            CHECK (es_contenedor_permanente = true OR puesto_id IS NOT NULL)
        ");

        Schema::table('postulantes', function (Blueprint $table) {
            $table->foreignId('puesto_id')
                  ->nullable()
                  ->after('convocatoria_id')
                  ->constrained('puestos')
                  ->nullOnDelete();

            $table->date('fecha_inscripcion')->nullable()->after('puesto_id');
        });

        // Las filas previas no tenían el dato: se toma la fecha de registro.
        DB::statement('UPDATE postulantes SET fecha_inscripcion = created_at::date WHERE fecha_inscripcion IS NULL');

        DB::statement('ALTER TABLE postulantes ALTER COLUMN fecha_inscripcion SET NOT NULL');

        DB::statement('ALTER TABLE postulantes DROP CONSTRAINT IF EXISTS uq_postulante_convocatoria');

        // Una inscripción por cédula, contenedor y año. EXTRACT sobre un date
        // es inmutable, así que sirve para un índice único.
        DB::statement("
            CREATE UNIQUE INDEX uq_postulante_convocatoria_anio
            ON postulantes (convocatoria_id, cedula, (EXTRACT(YEAR FROM fecha_inscripcion)))
            WHERE deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_postulante_convocatoria_anio');

        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('puesto_id');
            $table->dropColumn('fecha_inscripcion');
        });

        DB::statement('ALTER TABLE convocatorias DROP CONSTRAINT IF EXISTS convocatorias_puesto_segun_contenedor_check');

        // Los contenedores permanentes no tienen puesto: no pueden sobrevivir
        // a restaurar el NOT NULL.
        DB::table('convocatorias')->where('es_contenedor_permanente', true)->delete();

        DB::statement('ALTER TABLE convocatorias ALTER COLUMN puesto_id SET NOT NULL');

        Schema::table('convocatorias', function (Blueprint $table) {
            $table->dropColumn('es_contenedor_permanente');
        });

        DB::statement('ALTER TABLE postulantes ADD CONSTRAINT uq_postulante_convocatoria UNIQUE (convocatoria_id, cedula)');
    }
};
