<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            // Eliminar numero_contrato (sin datos)
            $table->dropColumn('numero_contrato');

            // Puede registrar marcación biométrica
            $table->boolean('puede_marcar')
                  ->default(true)
                  ->after('estado');

            // Código de marcación del contrato vigente
            // (desnormalizado para evitar JOINs en marcaciones)
            $table->string('codigo_marcacion', 50)
                  ->nullable()
                  ->after('puede_marcar');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('numero_contrato')->nullable();
            $table->dropColumn(['puede_marcar', 'codigo_marcacion']);
        });
    }
};
