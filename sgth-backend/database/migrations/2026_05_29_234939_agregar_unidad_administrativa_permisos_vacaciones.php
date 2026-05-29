<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // permisos_servidor
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->foreignId('unidad_administrativa_id')
                  ->nullable()
                  ->after('servidor_id')
                  ->constrained('unidades_administrativas')
                  ->nullOnDelete();
        });

        // vacaciones
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->foreignId('unidad_administrativa_id')
                  ->nullable()
                  ->after('servidor_id')
                  ->constrained('unidades_administrativas')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->dropForeign(['unidad_administrativa_id']);
            $table->dropColumn('unidad_administrativa_id');
        });

        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropForeign(['unidad_administrativa_id']);
            $table->dropColumn('unidad_administrativa_id');
        });
    }
};
