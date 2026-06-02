<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar codigo_marcacion de servidores
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn('codigo_marcacion');
        });

        // 2. Eliminar codigo_marcacion de contratos_servidor
        //    y agregar puede_marcar
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropColumn('codigo_marcacion');
            $table->boolean('puede_marcar')
                  ->default(true)
                  ->after('remuneracion');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('codigo_marcacion', 50)->nullable();
        });

        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->string('codigo_marcacion', 10)->nullable();
            $table->dropColumn('puede_marcar');
        });
    }
};
