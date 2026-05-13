<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ticket', 50)->unique();
            $table->foreignId('solicitante_id')->constrained('servidores')->restrictOnDelete();
            $table->string('tipo_ticket', 50); // incidente, solicitud_servicio, cambio, problema
            $table->string('categoria', 50);
            $table->foreignId('sla_id')->constrained('slas')->restrictOnDelete();
            $table->string('estado', 50)->default('nuevo'); // nuevo, asignado, en_progreso, escalado, resuelto, cerrado
            $table->string('asunto');
            $table->text('descripcion');
            $table->foreignId('bien_informatico_id')->nullable()->constrained('bienes_informaticos');
            $table->foreignId('tecnico_id')->nullable()->constrained('users');
            $table->datetime('fecha_vencimiento_sla')->nullable();
            $table->datetime('fecha_cierre')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('tickets'); }
};