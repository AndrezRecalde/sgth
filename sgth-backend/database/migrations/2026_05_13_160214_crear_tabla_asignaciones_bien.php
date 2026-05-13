<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('asignaciones_bien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->restrictOnDelete();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->date('fecha_asignacion');
            $table->date('fecha_devolucion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('url_acta_pdf')->nullable();
            $table->string('estado', 50)->default('activa');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('asignaciones_bien'); }
};