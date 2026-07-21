<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reemplazado por adjuntos reales en documentos_sso (Fase 9).
        Schema::table('cumplimiento_normativa', function (Blueprint $table) {
            $table->dropColumn('medio_verificacion');
        });

        Schema::table('programa_drogas_seguimiento', function (Blueprint $table) {
            $table->dropColumn('medio_verificacion');
        });
    }

    public function down(): void
    {
        Schema::table('cumplimiento_normativa', function (Blueprint $table) {
            $table->text('medio_verificacion')->nullable();
        });

        Schema::table('programa_drogas_seguimiento', function (Blueprint $table) {
            $table->text('medio_verificacion')->nullable();
        });
    }
};
