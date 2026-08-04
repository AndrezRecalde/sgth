<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'fecha_inscripcion' es NOT NULL porque de ella sale el año con que se
 * agrupan y filtran los aspirantes del reclutamiento express. Sin default, una
 * inserción que no la especifique revienta con un error de base de datos en
 * vez de hacer lo obvio.
 *
 * CURRENT_DATE es la semántica correcta: si no se indica otra cosa, el
 * aspirante se inscribió hoy — el mismo criterio con el que se rellenaron las
 * filas históricas (created_at::date).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE postulantes ALTER COLUMN fecha_inscripcion SET DEFAULT CURRENT_DATE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE postulantes ALTER COLUMN fecha_inscripcion DROP DEFAULT');
    }
};
