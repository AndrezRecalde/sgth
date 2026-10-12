<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las actividades laborales pasan al portal: el servidor anota las suyas, las
 * filtra y las exporta en PDF. Nadie las aprueba (decisión del 2026-10-12), así
 * que sobra el `estado` del borrador original —registrado, validado, observado,
 * aprobado—, que ninguna pantalla llegó a mover. Tampoco se anotan horas: la
 * actividad es del día, y el informe no suma duraciones.
 *
 * `informes_actividades` guardaba la ruta de un PDF que nunca se generaba: el
 * informe sale al vuelo del rango que se filtra, y no hay nada que guardar.
 * Las dos tablas estaban vacías: ninguna pantalla escribía en ellas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actividades_laborales', function (Blueprint $table) {
            $table->dropColumn(['estado', 'hora_inicio', 'hora_fin']);
            // El listado y el informe buscan siempre por los dos.
            $table->index(['servidor_id', 'fecha']);
        });

        Schema::dropIfExists('informes_actividades');
    }

    public function down(): void
    {
        Schema::create('informes_actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->integer('mes');
            $table->integer('anio');
            $table->string('url_pdf');
            $table->string('estado', 50)->default('generado');
            $table->foreignId('aprobado_por')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['servidor_id', 'mes', 'anio']);
        });

        Schema::table('actividades_laborales', function (Blueprint $table) {
            $table->dropIndex(['servidor_id', 'fecha']);
            $table->string('estado', 50)->default('registrado');
            // Sin los datos, que se perdieron al bajarlas: vuelven anulables.
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
        });
    }
};
