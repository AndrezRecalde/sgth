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
        Schema::create('odontograma_procedimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odontograma_pieza_id')
                ->constrained('odontograma_piezas')
                ->cascadeOnDelete();
            $table->foreignId('consulta_medica_id')
                ->nullable()
                ->constrained('consultas_medicas')
                ->nullOnDelete();
            $table->string('procedimiento', 40);
            $table->string('superficie', 20)->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('realizado_por')->constrained('users');
            $table->date('fecha');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('odontograma_pieza_id');
            $table->index('consulta_medica_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odontograma_procedimientos');
    }
};
