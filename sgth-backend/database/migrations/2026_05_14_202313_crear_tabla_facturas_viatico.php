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
        Schema::create('facturas_viatico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_viatico_id')->constrained('liquidaciones_viatico')->cascadeOnDelete();
            $table->string('concepto');
            $table->string('detalle')->nullable();
            $table->string('numero_factura');
            $table->string('ruc_proveedor', 13);
            $table->string('nombre_proveedor');
            $table->decimal('monto', 10, 2);
            $table->string('archivo_ruta')->nullable();
            $table->string('archivo_nombre')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas_viatico');
    }
};
