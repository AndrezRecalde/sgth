<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capacitaciones_sso', function (Blueprint $table) {
            $table->id();
            $table->string('tema');
            $table->date('fecha');
            $table->integer('duracion_horas');
            $table->string('instructor');
            $table->string('lugar')->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('fecha');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacitaciones_sso');
    }
};
