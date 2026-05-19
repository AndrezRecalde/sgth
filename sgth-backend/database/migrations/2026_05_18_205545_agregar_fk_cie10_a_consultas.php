<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->foreignId('diagnostico_cie10_id')->nullable()->after('diagnostico_cie10')->constrained('diagnosticos_cie10')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultas_medicas', function (Blueprint $table) {
            $table->dropForeign(['diagnostico_cie10_id']);
            $table->dropColumn('diagnostico_cie10_id');
        });
    }
};
