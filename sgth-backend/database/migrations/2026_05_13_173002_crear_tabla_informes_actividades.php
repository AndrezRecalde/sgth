<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('informes_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->integer('mes');
            $table->integer('anio');
            $table->string('url_pdf');
            $table->string('estado', 50)->default('generado'); // generado, firmado, aprobado
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['servidor_id', 'mes', 'anio']);
        });
    }
    public function down(): void { Schema::dropIfExists('informes_actividades'); }
};