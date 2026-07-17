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
        Schema::create('odontograma_piezas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odontograma_id')
                ->constrained('odontogramas')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_pieza');
            $table->string('denticion', 15);
            $table->string('condicion', 20);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['odontograma_id', 'numero_pieza']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('odontograma_piezas');
    }
};
