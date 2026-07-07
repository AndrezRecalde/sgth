<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alergias_paciente', function (Blueprint $table) {
            $table->timestamp('anulado_en')->nullable()->after('observacion');
            $table->foreignId('anulado_por')
                  ->nullable()->after('anulado_en')
                  ->constrained('users');
            $table->string('motivo_anulacion')->nullable()->after('anulado_por');
        });
    }

    public function down(): void
    {
        Schema::table('alergias_paciente', function (Blueprint $table) {
            $table->dropForeign(['anulado_por']);
            $table->dropColumn(['anulado_en', 'anulado_por', 'motivo_anulacion']);
        });
    }
};
