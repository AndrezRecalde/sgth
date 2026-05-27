<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar servidor_id a users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('servidor_id')
                  ->nullable()
                  ->after('activo')
                  ->constrained('servidores')
                  ->nullOnDelete();
        });

        // 2. Migrar datos existentes
        DB::statement('
            UPDATE users u
            SET servidor_id = s.id
            FROM servidores s
            WHERE s.user_id = u.id
        ');

        // 3. Eliminar user_id de servidores
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        // 4. Eliminar name de users
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['servidor_id']);
            $table->dropColumn('servidor_id');
            $table->string('name')->nullable()->after('id');
        });

        Schema::table('servidores', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }
};
