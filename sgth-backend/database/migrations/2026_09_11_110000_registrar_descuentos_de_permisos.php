<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De qué período salió cada hora de un permiso personal.
 *
 * El permiso descontaba solo del período del año de su fecha: si ese estaba
 * vacío se rechazaba aunque hubiera saldo de años anteriores, y los días viejos
 * seguían acercándose al tope. Ahora reparte igual que una vacación, del
 * período más antiguo al más nuevo, y esta tabla anota cuánto tomó de cada uno.
 *
 * Con la anotación, revertir la confirmación devuelve cada tramo a su período
 * tal como salió, sin volver a calcular las horas con los datos de ese momento.
 *
 * Es la misma forma que `vacacion_descuentos`: `devuelto_en` no borra la fila,
 * porque el descuento ocurrió y la devolución también.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permiso_descuentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permiso_servidor_id')->constrained('permisos_servidor')->cascadeOnDelete();
            // Sin cascada: borrar un período no puede llevarse en silencio el
            // rastro de lo que se descontó de él.
            $table->foreignId('periodo_vacacion_id')->constrained('periodos_vacaciones');
            $table->decimal('dias', 8, 2);
            $table->timestamp('devuelto_en')->nullable();
            $table->timestamps();

            $table->index(['permiso_servidor_id', 'devuelto_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permiso_descuentos');
    }
};
