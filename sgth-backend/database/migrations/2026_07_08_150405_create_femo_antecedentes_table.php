<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_antecedentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->enum('tipo', [
                'clinico', 'quirurgico', 'familiar',
                'ginecologico', 'reproductivo_masculino', 'otro',
            ]);
            $table->text('descripcion');
            $table->year('fecha_aproximada')->nullable();
            $table->timestamps();

            $table->index('ficha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_antecedentes');
    }
};
