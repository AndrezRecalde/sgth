<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adquisiciones_medicamentos', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique()->nullable();
            $table->string('tipo', 20); // 'compra' | 'donacion'
            $table->string('numero_documento', 100);
            $table->string('proveedor_o_donante', 255);
            $table->date('fecha_adquisicion');
            $table->text('observaciones')->nullable();
            $table->string('documento_respaldo')->nullable();
            $table->foreignId('registrado_por')
                  ->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tipo');
            $table->index('fecha_adquisicion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adquisiciones_medicamentos');
    }
};
