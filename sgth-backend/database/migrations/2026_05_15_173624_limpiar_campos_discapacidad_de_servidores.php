<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_discapacidad',
                'porcentaje_discapacidad',
                'numero_carnet_conadis',
                'carnet_conadis_ruta',
                'carnet_conadis_vencimiento'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('tipo_discapacidad')->nullable();
            $table->decimal('porcentaje_discapacidad', 5, 2)->nullable();
            $table->string('numero_carnet_conadis')->nullable();
            $table->string('carnet_conadis_ruta')->nullable();
            $table->date('carnet_conadis_vencimiento')->nullable();
        });
    }
};
