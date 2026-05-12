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
        Schema::create('documentos_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->onDelete('cascade');
            
            $table->enum('tipo_documento', [
                'cedula_identidad',
                'papeleta_votacion',
                'titulo_tercer_nivel',
                'titulo_cuarto_nivel',
                'certificado_trabajo_anterior',
                'carnet_conadis',
                'certificado_enfermedad_catastrofica',
                'contrato_laboral',
                'nombramiento',
                'certificado_medico',
                'otro'
            ]);
            
            $table->string('nombre_archivo'); // Nombre original del archivo
            $table->string('ruta_archivo');   // Ruta física en el storage
            $table->unsignedBigInteger('tamanio_bytes')->nullable();
            $table->string('mime_type')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('descripcion')->nullable();
            
            $table->boolean('estado')->default(true);
            $table->foreignId('subido_por')->constrained('users');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_servidor');
    }
};
