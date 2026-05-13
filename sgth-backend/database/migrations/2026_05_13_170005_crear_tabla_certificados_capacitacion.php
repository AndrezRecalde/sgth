<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('certificados_capacitacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->unique()->constrained('inscripciones_curso')->restrictOnDelete();
            $table->string('codigo_certificado', 100)->unique();
            $table->string('url_pdf');
            $table->date('fecha_emision');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('certificados_capacitacion'); }
};