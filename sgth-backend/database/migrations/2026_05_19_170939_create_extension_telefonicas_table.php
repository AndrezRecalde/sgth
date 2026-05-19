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
        Schema::create('extensiones_telefonicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_administrativa_id')
                  ->constrained('unidades_administrativas')
                  ->cascadeOnDelete();
            $table->string('numero_extension', 10);
            $table->string('responsable', 100);
            $table->string('descripcion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extensiones_telefonicas');
    }
};
