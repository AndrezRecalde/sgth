<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('femo_factores_riesgo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ficha_id')
                  ->constrained('fichas_salud_ocupacional')
                  ->cascadeOnDelete();
            $table->enum('categoria', [
                'fisico', 'seguridad', 'quimico',
                'biologico', 'ergonomico', 'psicosocial',
            ]);
            $table->string('factor', 200);
            $table->boolean('presente')->default(true);
            $table->string('medida_preventiva', 500)->nullable();
            $table->timestamps();

            $table->index(['ficha_id', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('femo_factores_riesgo');
    }
};
