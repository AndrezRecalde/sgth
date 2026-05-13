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
        Schema::create('bienes_informaticos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_qr', 100)->unique();
            $table->string('codigo_institucional', 100)->unique();
            $table->string('tipo_bien', 50); // computador, laptop, tablet, etc.
            $table->string('marca', 100);
            $table->string('modelo', 100);
            $table->string('numero_serie', 100)->unique();
            $table->string('estado', 50)->default('activo'); // activo, en_mantenimiento, dado_de_baja, robado, perdido
            $table->date('fecha_compra')->nullable();
            $table->date('garantia_hasta')->nullable();
            $table->string('proveedor')->nullable();
            $table->json('caracteristicas_tecnicas')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bienes_informaticos');
    }
};
