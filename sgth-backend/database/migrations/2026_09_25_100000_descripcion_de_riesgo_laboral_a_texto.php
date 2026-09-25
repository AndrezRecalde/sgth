<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'descripcion' de riesgos_laborales pasa de varchar(255) a text.
 *
 * La columna nació como `$table->string('descripcion')` —255 caracteres—, pero
 * tanto StoreRiesgoLaboralRequest como el esquema Zod del frontend aceptan
 * 2000, y el modal la pinta como un Textarea. Describir un riesgo NTP 330 pasa
 * de 255 con facilidad, y entonces PostgreSQL cortaba con un 22001
 * («value too long for type character varying(255)») que sale como 500: el
 * usuario veía «No se pudo registrar el riesgo laboral», sin saber qué campo
 * lo impedía, y perdía el texto que había escrito.
 *
 * Se elige ensanchar la columna y no bajar el límite a 255 porque 255 es poco
 * para lo que el campo pide; 'medidas_preventivas', su gemelo en el mismo
 * formulario, ya es text. El techo real de 2000 lo sigue imponiendo la
 * validación, que es donde el usuario recibe el error en su campo.
 *
 * Se usa SQL directo en vez de ->change(): doctrine/dbal no está instalado y
 * el cambio es una sola columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE riesgos_laborales ALTER COLUMN descripcion TYPE text');
    }

    public function down(): void
    {
        // Volver a varchar(255) no cabe sin perder datos: lo que se escribió
        // por encima de 255 se recorta antes de estrechar la columna.
        DB::statement('UPDATE riesgos_laborales SET descripcion = LEFT(descripcion, 255) WHERE LENGTH(descripcion) > 255');

        DB::statement('ALTER TABLE riesgos_laborales ALTER COLUMN descripcion TYPE varchar(255)');
    }
};
