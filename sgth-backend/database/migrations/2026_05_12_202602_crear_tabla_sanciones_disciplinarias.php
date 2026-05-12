<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanciones_disciplinarias', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('sumario_id')->unique()->constrained('sumarios')->onDelete('cascade');
            
            $table->enum('tipo_falta', ['leve', 'grave', 'muy_grave']);
            
            $table->enum('tipo_sancion', [
                'amonestacion_verbal',
                'amonestacion_escrita',
                'multa',
                'suspension',
                'destitucion'
            ]);
            
            $table->decimal('porcentaje_multa', 5, 2)->nullable(); // Max 10.00
            $table->integer('dias_suspension')->nullable(); // Max 30
            
            $table->date('fecha_efectiva');
            $table->text('observaciones')->nullable();
            
            // Campos estándar
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanciones_disciplinarias');
    }
};
