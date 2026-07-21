<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epp_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->foreignId('equipo_proteccion_id')->constrained('equipos_proteccion')->restrictOnDelete();
            $table->date('fecha_entrega');
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->string('motivo', 20);
            $table->foreignId('entregado_por')->constrained('users');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['servidor_id', 'fecha_entrega']);
            $table->index('equipo_proteccion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epp_entregas');
    }
};
