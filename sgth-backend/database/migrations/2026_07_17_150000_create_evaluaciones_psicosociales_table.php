<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_psicosociales', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7); // 'YYYY' (año) o 'YYYY-MM' (mes)
            $table->foreignId('unidad_administrativa_id')
                ->nullable()
                ->constrained('unidades_administrativas')
                ->nullOnDelete();
            $table->string('codigo_acceso', 20)->unique();
            $table->date('fecha_apertura');
            $table->date('fecha_cierre')->nullable();
            $table->boolean('activa')->default(true);
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamps();

            $table->index('activa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_psicosociales');
    }
};
