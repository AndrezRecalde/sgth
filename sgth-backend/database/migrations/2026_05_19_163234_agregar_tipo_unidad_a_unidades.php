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
        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->string('acronimo', 50)->nullable()->after('nombre');
            $table->uuid('tipo_unidad_id')->nullable()->after('acronimo');
            
            $table->foreign('tipo_unidad_id')
                  ->references('id')
                  ->on('tipos_unidad')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('unidades_administrativas', function (Blueprint $table) {
            $table->dropForeign(['tipo_unidad_id']);
            $table->dropColumn(['tipo_unidad_id', 'acronimo']);
        });
    }
};
