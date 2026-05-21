<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discapacidades_carga_familiar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_familiar_id')
                  ->constrained('cargas_familiares')
                  ->cascadeOnDelete();

            $table->enum('tipo_discapacidad', [
                'fisica', 'sensorial', 'intelectual',
                'psicosocial', 'visceral', 'multiple'
            ]);
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->string('numero_carnet_conadis')->nullable();
            $table->string('carnet_ruta')->nullable();
            $table->string('carnet_nombre_archivo')->nullable();
            $table->date('carnet_vencimiento')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discapacidades_carga_familiar');
    }
};