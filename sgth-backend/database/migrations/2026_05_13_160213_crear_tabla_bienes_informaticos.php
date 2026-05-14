<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('bienes_informaticos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_qr', 100)->unique();
            $table->string('codigo_institucional', 100)->unique();
            $table->foreignId('tipo_bien_id')->constrained('tipos_bien')->restrictOnDelete();
            $table->foreignId('marca_id')->constrained('marcas')->restrictOnDelete();
            $table->foreignId('origen_bien_id')->constrained('origenes_bien')->restrictOnDelete();
            $table->string('modelo', 100)->nullable();
            $table->string('numero_serie', 100)->unique();
            $table->string('estado_operativo', 50)->default('activo'); // activo, en_mantenimiento, dado_de_baja, robado, perdido
            $table->string('condicion_fisica', 50)->default('bueno'); // bueno, regular, malo
            $table->date('fecha_fin_vida_util')->nullable(); // Calculado auto
            $table->json('caracteristicas_tecnicas')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('bienes_informaticos'); }
};