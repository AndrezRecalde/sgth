<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifas_viatico', function (Blueprint $table) {
            $table->unique(
                ['zona', 'nivel', 'tipo_tarifa'],
                'tarifas_viatico_zona_nivel_tipo_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tarifas_viatico', function (Blueprint $table) {
            $table->dropUnique(
                'tarifas_viatico_zona_nivel_tipo_unique'
            );
        });
    }
};
