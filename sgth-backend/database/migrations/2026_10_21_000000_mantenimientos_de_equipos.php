<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los mantenimientos de los equipos (docs/diseno-subsistema-tic.md, fase 1.5).
 *
 * - `realizado_por`: TIC o el proveedor. El del proveedor va ligado al
 *   contrato de vigencia tecnológica que lo cubre.
 * - Anulación en lugar de borrado, como en el resto del sistema: un
 *   mantenimiento registrado por error queda anulado con su motivo y no
 *   desaparece del historial del equipo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mantenimientos_bien', function (Blueprint $table) {
            $table->string('realizado_por', 20)->default('tic')->after('tipo_mantenimiento');
            $table->foreignId('contrato_vigencia_id')->nullable()->after('realizado_por')
                ->constrained('contratos_vigencia')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users');
            $table->string('motivo_anulacion', 500)->nullable();
            $table->index(['bien_informatico_id', 'fecha_mantenimiento']);
        });
    }

    public function down(): void
    {
        Schema::table('mantenimientos_bien', function (Blueprint $table) {
            $table->dropIndex(['bien_informatico_id', 'fecha_mantenimiento']);
            $table->dropForeign(['contrato_vigencia_id']);
            $table->dropForeign(['anulado_por']);
            $table->dropColumn(['realizado_por', 'contrato_vigencia_id', 'anulado_en', 'anulado_por', 'motivo_anulacion']);
        });
    }
};
