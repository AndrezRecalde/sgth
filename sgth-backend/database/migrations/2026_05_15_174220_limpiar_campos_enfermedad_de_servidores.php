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
                'tipo_enfermedad_catastrofica',
                'enfermedad_catastrofica_certificado_ruta'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('tipo_enfermedad_catastrofica')->nullable();
            $table->string('enfermedad_catastrofica_certificado_ruta')->nullable();
        });
    }
};
