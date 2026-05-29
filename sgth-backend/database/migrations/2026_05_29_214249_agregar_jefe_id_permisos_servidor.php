<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->foreignId('jefe_id')
                  ->nullable()->after('servidor_id')
                  ->constrained('servidores')->nullOnDelete();
            $table->foreignId('creado_por')
                  ->nullable()->after('jefe_id')
                  ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permisos_servidor', function (Blueprint $table) {
            $table->dropForeign(['jefe_id']);
            $table->dropForeign(['creado_por']);
            $table->dropColumn(['jefe_id','creado_por']);
        });
    }
};
