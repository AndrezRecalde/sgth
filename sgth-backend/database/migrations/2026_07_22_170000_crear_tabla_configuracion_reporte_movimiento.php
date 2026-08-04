<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué tipo_movimiento es reportable a SIITH/SUT no está definido de
     * forma estable todavía (pendiente de confirmar formato exacto con la
     * UATH del GAD) — se modela como tabla editable en vez de hardcodear
     * la lista en código.
     */
    public function up(): void
    {
        Schema::create('configuracion_reporte_movimiento', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_movimiento', 50)->unique();
            $table->boolean('reportable_siith')->default(false);
            $table->boolean('reportable_sut')->default(false);
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_reporte_movimiento');
    }
};
