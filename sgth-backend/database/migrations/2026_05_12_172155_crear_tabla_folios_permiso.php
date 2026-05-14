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
        Schema::create('folios_permiso', function (Blueprint $table) {
            $table->id();
            
            // Relación estricta uno-a-uno con el permiso
            $table->foreignId('permiso_id')->unique()->constrained('permisos_servidor')->onDelete('cascade');
            
            // El folio generado (ej: PER-2026-00045)
            $table->string('folio')->unique();
            
            // Ruta del QR generado (que apuntará a la URL pública de validación)
            $table->string('qr_ruta')->nullable();
            
            $table->timestamps();
            
            // NOTA: SIN softDeletes, la trazabilidad del documento es inmutable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folios_permiso');
    }
};
