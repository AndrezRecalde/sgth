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
        Schema::create('comisiones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_comision')->unique();
            $table->text('motivo');
            $table->foreignId('unidad_administrativa_id')->constrained('unidades_administrativas');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('documento_autorizacion')->nullable();
            $table->enum('estado', ['borrador', 'activa', 'cerrada'])->default('borrador');
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comisiones');
    }
};
