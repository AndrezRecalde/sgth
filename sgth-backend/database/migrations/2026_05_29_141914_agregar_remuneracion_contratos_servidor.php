<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->decimal('remuneracion', 10, 2)
                  ->nullable()
                  ->after('fecha_fin')
                  ->comment('Remuneración mensual unificada en el momento del contrato');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropColumn('remuneracion');
        });
    }
};
