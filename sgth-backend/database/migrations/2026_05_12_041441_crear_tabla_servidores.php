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
        Schema::create('servidores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('cedula', 10)->unique();
            $table->string('nombre');
            $table->string('segundo_nombre')->nullable();
            $table->string('apellido');
            $table->string('segundo_apellido')->nullable();
            $table->string('regimen_laboral');
            $table->unsignedBigInteger('unidad_administrativa_id')->nullable();
            $table->unsignedBigInteger('puesto_id')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servidores');
    }
};
