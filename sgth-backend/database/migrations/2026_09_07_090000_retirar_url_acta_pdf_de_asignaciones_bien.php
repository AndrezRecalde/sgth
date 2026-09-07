<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `url_acta_pdf` guardaba una ruta a un archivo que nunca existió.
     *
     * El servicio la componía a mano al crear la asignación
     * —`/storage/actas/acta_entrega_{id}.pdf`— y nadie generaba ese PDF. La
     * columna solo podía contener una promesa falsa.
     *
     * El acta ahora se arma bajo demanda y no se archiva, igual que el resto
     * de documentos del sistema, así que no hay ninguna ruta que guardar.
     */
    public function up(): void
    {
        Schema::table('asignaciones_bien', function (Blueprint $table) {
            $table->dropColumn('url_acta_pdf');
        });
    }

    public function down(): void
    {
        Schema::table('asignaciones_bien', function (Blueprint $table) {
            // Vuelve vacía: los valores que tenía no apuntaban a ningún
            // archivo, así que recuperarlos sería recuperar el error.
            $table->string('url_acta_pdf')->nullable()->after('observaciones');
        });
    }
};
