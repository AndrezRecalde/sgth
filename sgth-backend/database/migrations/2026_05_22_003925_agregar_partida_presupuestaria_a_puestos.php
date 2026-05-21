<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->foreignId('partida_presupuestaria_id')
                  ->nullable()
                  ->after('grupo_ocupacional_id')
                  ->constrained('partidas_presupuestarias')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->dropForeign(['partida_presupuestaria_id']);
            $table->dropColumn('partida_presupuestaria_id');
        });
    }
};
