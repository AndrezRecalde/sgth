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
        // PASO 1 - En tabla servidores
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropForeign(['ciudad_nacimiento_id']);
            $table->dropColumn('ciudad_nacimiento_id');
            $table->foreignId('canton_nacimiento_id')->nullable()->constrained('cantones');
        });

        // PASO 2 - En tabla destinos_viatico
        Schema::table('destinos_viatico', function (Blueprint $table) {
            $table->dropForeign(['ciudad_id']);
            $table->dropColumn('ciudad_id');
            $table->foreignId('canton_id')->nullable()->constrained('cantones');
        });

        // PASO 3 - En tabla transportes_viatico
        Schema::table('transportes_viatico', function (Blueprint $table) {
            $table->dropForeign(['ciudad_origen_id']);
            $table->dropForeign(['ciudad_destino_id']);
            $table->dropColumn(['ciudad_origen_id', 'ciudad_destino_id']);

            $table->unsignedBigInteger('canton_origen_id')->nullable();
            $table->foreign('canton_origen_id')->references('id')->on('cantones')->nullOnDelete();

            $table->unsignedBigInteger('canton_destino_id')->nullable();
            $table->foreign('canton_destino_id')->references('id')->on('cantones')->nullOnDelete();
        });

        // PASO 4 - Eliminar tabla ciudades
        Schema::dropIfExists('ciudades');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('ciudades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')->constrained('provincias')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('codigo')->nullable();
            $table->timestamps();
            
            $table->index('provincia_id');
        });

        Schema::table('transportes_viatico', function (Blueprint $table) {
            $table->dropForeign(['canton_origen_id']);
            $table->dropForeign(['canton_destino_id']);
            $table->dropColumn(['canton_origen_id', 'canton_destino_id']);

            $table->unsignedBigInteger('ciudad_origen_id')->nullable();
            $table->foreign('ciudad_origen_id')->references('id')->on('ciudades')->nullOnDelete();

            $table->unsignedBigInteger('ciudad_destino_id')->nullable();
            $table->foreign('ciudad_destino_id')->references('id')->on('ciudades')->nullOnDelete();
        });

        Schema::table('destinos_viatico', function (Blueprint $table) {
            $table->dropForeign(['canton_id']);
            $table->dropColumn('canton_id');
            $table->foreignId('ciudad_id')->nullable()->constrained('ciudades');
        });

        Schema::table('servidores', function (Blueprint $table) {
            $table->dropForeign(['canton_nacimiento_id']);
            $table->dropColumn('canton_nacimiento_id');
            $table->foreignId('ciudad_nacimiento_id')->nullable()->constrained('ciudades');
        });
    }
};
