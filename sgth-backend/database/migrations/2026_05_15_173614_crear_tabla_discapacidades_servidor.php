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
        Schema::create('discapacidades_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            
            $table->enum('tipo_discapacidad', [
                'fisica',
                'sensorial',
                'intelectual',
                'psicosocial',
                'visceral',
                'multiple'
            ]);
            
            $table->decimal('porcentaje', 5, 2);
            $table->string('numero_carnet_conadis');
            $table->string('carnet_ruta')->nullable();
            $table->string('carnet_nombre_archivo')->nullable();
            $table->date('carnet_vencimiento')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discapacidades_servidor');
    }
};
