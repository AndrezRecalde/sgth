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
        Schema::create('atenciones_enfermeria', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 30)->unique()->nullable();
            $table->foreignId('enfermera_id')
                  ->constrained('users');
            $table->foreignId('servidor_id')
                  ->nullable()
                  ->constrained('servidores')
                  ->nullOnDelete();
            $table->foreignId('carga_familiar_id')
                  ->nullable()
                  ->constrained('cargas_familiares')
                  ->nullOnDelete();
            $table->foreignId('catalogo_servicio_id')
                  ->constrained('catalogo_servicios_enfermeria');
            $table->text('descripcion')->nullable();
            $table->timestamp('atendido_en');
            $table->foreignId('created_by')
                  ->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('enfermera_id');
            $table->index('servidor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('atenciones_enfermeria');
    }
};
