<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargas_familiares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')
                  ->constrained('servidores')
                  ->cascadeOnDelete();

            $table->string('apellidos', 100);
            $table->string('nombres', 100);
            $table->enum('parentesco', ['conyugue', 'hijo']);
            $table->date('fecha_nacimiento');
            $table->boolean('persona_con_discapacidad')->default(false);
            $table->boolean('posee_enfermedad_catastrofica')->default(false);
            $table->text('observaciones')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_familiares');
    }
};