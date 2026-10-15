<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las fotografías de los servidores (docs/diseno-comunicacion-social.md, 5.1
 * y 7.2; fase 1.1).
 *
 * Una vigente por servidor y las anteriores en el historial: reemplazar no
 * borra, y una foto anterior se puede restaurar. Los archivos van al disco
 * privado, ya recortados a 3:4 (600×800) con su miniatura cuadrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotografias_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->string('archivo');
            $table->string('miniatura');
            $table->string('origen', 20);
            $table->boolean('vigente')->default(false);
            // Cuándo y quién la dejó vigente: al subirla o al restaurarla.
            $table->timestamp('activada_en')->nullable();
            $table->foreignId('activada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['servidor_id', 'created_at']);
        });

        // Una sola vigente por servidor. Dos subidas a la vez chocarían aquí
        // en vez de dejar dos fotos vigentes.
        DB::statement(
            'CREATE UNIQUE INDEX fotografias_servidor_una_vigente '
            .'ON fotografias_servidor (servidor_id) WHERE vigente'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('fotografias_servidor');
    }
};
