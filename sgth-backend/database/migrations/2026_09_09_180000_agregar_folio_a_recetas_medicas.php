<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El número con el que se identifica una receta impresa.
 *
 * Hasta ahora la receta solo se veía en pantalla y le bastaba su `id`. Al
 * imprimirse pasa a ser un documento que sale del dispensario y vuelve —el
 * paciente lo trae al mostrador, y a veces a una farmacia externa—, así que
 * necesita un número que se pueda dictar por teléfono, escribir a mano y
 * cotejar sin ambigüedad. El `id` no sirve para eso: no dice de qué año es ni
 * de qué documento se está hablando.
 *
 * Mismo formato y mismas reglas que el folio del certificado médico, que ya
 * resolvió esto: prefijo, año y secuencial de cinco cifras.
 *
 * Las recetas que ya existen se numeran hacia atrás por orden de emisión, para
 * que lo ya recetado también se pueda imprimir con folio. Entran también las
 * borradas en blando: comparten la tabla y, por tanto, el índice único, y
 * saltárselas dejaría huecos que la siguiente emisión volvería a ocupar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->string('folio', 20)->nullable()->after('id');
        });

        // Numera lo ya emitido por año y por orden de emisión. `row_number()`
        // lo resuelve en una sola pasada, sin traerse las recetas a PHP.
        DB::statement("
            UPDATE recetas_medicas AS r
            SET folio = n.folio
            FROM (
                SELECT
                    id,
                    'REC-' || EXTRACT(YEAR FROM created_at)::int || '-' ||
                    LPAD(
                        ROW_NUMBER() OVER (
                            PARTITION BY EXTRACT(YEAR FROM created_at)
                            ORDER BY id
                        )::text,
                        5, '0'
                    ) AS folio
                FROM recetas_medicas
            ) AS n
            WHERE r.id = n.id
        ");

        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->unique('folio');
        });
    }

    public function down(): void
    {
        Schema::table('recetas_medicas', function (Blueprint $table) {
            $table->dropUnique(['folio']);
            $table->dropColumn('folio');
        });
    }
};
