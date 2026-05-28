<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_bancarias_servidor', function (Blueprint $table) {
            $table->dropColumn('nombre_banco_otro');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_bancarias_servidor', function (Blueprint $table) {
            $table->string('nombre_banco_otro', 255)->nullable()->after('entidad_financiera_id');
        });
    }
};
