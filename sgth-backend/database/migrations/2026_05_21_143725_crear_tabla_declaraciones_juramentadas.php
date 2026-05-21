<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('declaraciones_juramentadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->date('fecha_declaracion');
            $table->string('codigo_barras', 100);
            $table->enum('tipo_declaracion', [
                'inicio_gestion', 'periodica', 'fin_gestion'
            ]);
            $table->string('documento_ruta')->nullable();
            $table->string('documento_nombre_archivo')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('declaraciones_juramentadas');
    }
};