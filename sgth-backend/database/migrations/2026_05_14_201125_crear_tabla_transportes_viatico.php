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
        Schema::create('transportes_viatico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatico_id')->constrained('viaticos')->cascadeOnDelete();
            
            $table->enum('tipo', [
                'bus_interprovincial', 
                'avion', 
                'vehiculo_propio', 
                'taxi', 
                'transporte_institucional', 
                'otro'
            ]);

            // FKs Geográficas Origen
            $table->unsignedBigInteger('provincia_origen_id')->nullable();
            $table->foreign('provincia_origen_id')->references('id')->on('provincias')->nullOnDelete();
            
            $table->unsignedBigInteger('ciudad_origen_id')->nullable();
            $table->foreign('ciudad_origen_id')->references('id')->on('ciudades')->nullOnDelete();

            // FKs Geográficas Destino
            $table->unsignedBigInteger('provincia_destino_id')->nullable();
            $table->foreign('provincia_destino_id')->references('id')->on('provincias')->nullOnDelete();
            
            $table->unsignedBigInteger('ciudad_destino_id')->nullable();
            $table->foreign('ciudad_destino_id')->references('id')->on('ciudades')->nullOnDelete();

            $table->string('pais_origen')->nullable();
            $table->string('pais_destino')->nullable();
            
            $table->dateTime('fecha_viaje');
            $table->string('empresa_o_aerolinea')->nullable();
            $table->string('numero_ticket_o_billete')->nullable();
            
            $table->string('placa_vehiculo')->nullable();
            $table->decimal('kilometraje', 8, 2)->nullable();
            $table->decimal('valor_kilometro', 8, 2)->nullable();
            
            $table->decimal('monto', 10, 2);
            $table->string('archivo_respaldo')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transportes_viatico');
    }
};
