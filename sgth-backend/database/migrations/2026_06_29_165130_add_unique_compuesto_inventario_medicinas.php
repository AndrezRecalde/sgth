<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_medicinas', function (Blueprint $table) {
            $table->unique(
                ['nombre', 'presentacion', 'concentracion'],
                'unique_nombre_presentacion_concentracion'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventario_medicinas', function (Blueprint $table) {
            $table->dropUnique('unique_nombre_presentacion_concentracion');
        });
    }
};
