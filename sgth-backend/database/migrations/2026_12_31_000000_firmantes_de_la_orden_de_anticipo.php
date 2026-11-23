<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La orden de pago del anticipo entra en la tabla de firmantes.
 *
 * `viatico_firmantes` nació con dos restricciones que enumeran los documentos
 * y los roles del trámite, y la orden añade uno de cada: el documento
 * `orden_anticipo` y el rol `elaborado`, que no sale del organigrama —ningún
 * puesto es «quien elabora»— sino de quien genera la orden.
 *
 * Pedida por Gestión Financiera (2026-11-23, S3 y S4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE viatico_firmantes DROP CONSTRAINT viatico_firmante_documento_check');
        DB::statement("
            ALTER TABLE viatico_firmantes
            ADD CONSTRAINT viatico_firmante_documento_check
            CHECK (documento IN ('solicitud', 'informe', 'comprobante', 'orden_anticipo'))
        ");

        DB::statement('ALTER TABLE viatico_firmantes DROP CONSTRAINT viatico_firmante_rol_check');
        DB::statement("
            ALTER TABLE viatico_firmantes
            ADD CONSTRAINT viatico_firmante_rol_check
            CHECK (rol IN ('maxima_autoridad', 'jefe_unidad', 'director_financiero', 'elaborado'))
        ");
    }

    public function down(): void
    {
        // Las firmas de la orden dejarían la tabla fuera de la restricción
        // anterior: se van con ella.
        DB::table('viatico_firmantes')->where('documento', 'orden_anticipo')->delete();

        DB::statement('ALTER TABLE viatico_firmantes DROP CONSTRAINT viatico_firmante_documento_check');
        DB::statement("
            ALTER TABLE viatico_firmantes
            ADD CONSTRAINT viatico_firmante_documento_check
            CHECK (documento IN ('solicitud', 'informe', 'comprobante'))
        ");

        DB::statement('ALTER TABLE viatico_firmantes DROP CONSTRAINT viatico_firmante_rol_check');
        DB::statement("
            ALTER TABLE viatico_firmantes
            ADD CONSTRAINT viatico_firmante_rol_check
            CHECK (rol IN ('maxima_autoridad', 'jefe_unidad', 'director_financiero'))
        ");
    }
};
