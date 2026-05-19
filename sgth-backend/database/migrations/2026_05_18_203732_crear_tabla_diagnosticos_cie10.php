<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosticos_cie10', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('descripcion');
            $table->string('categoria', 3);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            $table->index('codigo');
            $table->index('categoria');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosticos_cie10');
    }
};
