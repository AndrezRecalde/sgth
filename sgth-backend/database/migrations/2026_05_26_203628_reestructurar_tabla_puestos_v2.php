<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->foreignId('cargo_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('cargos')
                  ->nullOnDelete();

            $table->dropColumn(['denominacion', 'mision']);
        });
    }

    public function down(): void
    {
        Schema::table('puestos', function (Blueprint $table) {
            $table->dropForeign(['cargo_id']);
            $table->dropColumn('cargo_id');
            $table->string('denominacion', 255)->nullable();
            $table->text('mision')->nullable();
        });
    }
};
