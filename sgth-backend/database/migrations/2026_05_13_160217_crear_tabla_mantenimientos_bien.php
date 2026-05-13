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
        Schema::create('mantenimientos_bien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bien_informatico_id')->constrained('bienes_informaticos')->restrictOnDelete();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('tipo_mantenimiento', 50); // preventivo, correctivo
            $table->date('fecha_mantenimiento');
            $table->foreignId('tecnico_id')->nullable()->constrained('users');
            $table->text('descripcion');
            $table->decimal('costo', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('bien_informatico_id');
            $table->index('tecnico_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mantenimientos_bien');
    }
};
