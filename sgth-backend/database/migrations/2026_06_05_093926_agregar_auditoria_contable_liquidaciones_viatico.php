<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liquidaciones_viatico', function (Blueprint $table) {
            // Quién autorizó/revisó como jefe financiero
            $table->foreignId('jefe_financiero_id')
                  ->nullable()
                  ->after('observaciones')
                  ->constrained('users')
                  ->nullOnDelete();

            // Snapshot del cargo en el momento de contabilizar
            $table->string('cargo_jefe_financiero', 200)
                  ->nullable()
                  ->after('jefe_financiero_id');

            // Quién ejecutó la acción contabilizar
            $table->foreignId('contabilizado_por')
                  ->nullable()
                  ->after('cargo_jefe_financiero')
                  ->constrained('users')
                  ->nullOnDelete();

            // Cuándo se contabilizó
            $table->date('fecha_contabilizacion')
                  ->nullable()
                  ->after('contabilizado_por');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_viatico', function (Blueprint $table) {
            $table->dropForeign(['jefe_financiero_id']);
            $table->dropForeign(['contabilizado_por']);
            $table->dropColumn([
                'jefe_financiero_id',
                'cargo_jefe_financiero',
                'contabilizado_por',
                'fecha_contabilizacion',
            ]);
        });
    }
};
