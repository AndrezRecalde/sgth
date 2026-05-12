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
        Schema::create('movimientos_personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            
            $table->enum('tipo_movimiento', [
                'traslado',
                'ascenso',
                'subrogacion',
                'comision_servicios',
                'cambio_regimen',
                'cambio_puesto',
                'ingreso',
                'egreso'
            ]);
            
            $table->text('descripcion');
            $table->date('fecha_efectiva');
            
            $table->foreignId('unidad_origen_id')->nullable()->constrained('unidades_administrativas');
            $table->foreignId('unidad_destino_id')->nullable()->constrained('unidades_administrativas');
            $table->foreignId('puesto_origen_id')->nullable()->constrained('puestos');
            $table->foreignId('puesto_destino_id')->nullable()->constrained('puestos');
            
            $table->string('resolucion_numero')->nullable();
            $table->string('documento_respaldo')->nullable();
            
            $table->foreignId('autorizado_por')->constrained('users');
            $table->text('observacion')->nullable();
            
            $table->timestamps(); // SIN softDeletes para garantizar inmutabilidad histórica
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_personal');
    }
};
