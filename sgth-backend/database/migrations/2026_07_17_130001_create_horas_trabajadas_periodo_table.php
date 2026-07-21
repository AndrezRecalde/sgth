<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horas_trabajadas_periodo', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7); // 'YYYY' (año) o 'YYYY-MM' (mes)
            $table->foreignId('unidad_administrativa_id')->nullable()
                ->constrained('unidades_administrativas')->nullOnDelete();
            $table->unsignedInteger('total_horas');
            $table->foreignId('registrado_por')->constrained('users');
            $table->timestamps();

            $table->unique(['periodo', 'unidad_administrativa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_trabajadas_periodo');
    }
};
