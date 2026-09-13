<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La revisión de Financiero sobre cada comprobante de la liquidación.
 *
 * Hasta ahora Financiero solo podía contabilizar la liquidación entera o
 * devolverla, sin dejar constancia de qué comprobante estaba mal ni por qué.
 *
 * Decidido con el usuario: cada comprobante se acepta u observa con motivo, y
 * solo se contabiliza con todos aceptados. Los comprobantes que ya existen
 * nacen pendientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_viatico', function (Blueprint $table) {
            $table->string('estado_revision', 20)->default('pendiente')->after('monto');
            $table->text('observacion_revision')->nullable()->after('estado_revision');
            $table->foreignId('revisado_por')->nullable()->after('observacion_revision')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
        });

        DB::statement(
            "ALTER TABLE facturas_viatico ADD CONSTRAINT facturas_viatico_estado_revision_check "
            . "CHECK (estado_revision IN ('pendiente', 'aceptada', 'observada'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE facturas_viatico DROP CONSTRAINT IF EXISTS facturas_viatico_estado_revision_check');

        Schema::table('facturas_viatico', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['estado_revision', 'observacion_revision', 'revisado_en']);
        });
    }
};
