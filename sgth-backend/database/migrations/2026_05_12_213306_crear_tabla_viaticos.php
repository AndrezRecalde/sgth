<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viaticos', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('restrict');
            
            $table->enum('zona', ['dentro_provincia', 'fuera_provincia', 'exterior']);
            $table->enum('tipo', ['con_pernocte', 'sin_pernocte']);
            $table->string('destino');
            
            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin');
            $table->text('justificacion');
            
            $table->enum('estado', [
                'solicitado',
                'aprobado_jefe',
                'aprobado_director',
                'aprobado_autoridad',
                'aprobado_uath',
                'aprobado_financiero',
                'con_anticipo',
                'en_comision',
                'pendiente_liquidacion',
                'liquidado',
                'contabilizado'
            ])->default('solicitado');
            
            $table->decimal('monto_calculado', 8, 2)->default(0.00);
            $table->decimal('monto_anticipo', 8, 2)->default(0.00);
            
            $table->string('numero_resolucion')->nullable();
            $table->string('partida_presupuestaria')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viaticos');
    }
};
