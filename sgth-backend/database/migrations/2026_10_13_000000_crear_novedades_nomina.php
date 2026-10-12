<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las novedades para nómina (diseño de Acciones de Personal, 8.5 y 9.8; fase
 * 7.3a).
 *
 * Financiero paga en su propio sistema (decisión de TH, 2026-10-04): lo que el
 * SGTH le da cada mes son las novedades, con su monto de referencia. Se derivan
 * de los actos —la entrada y la salida, el cambio de remuneración, los días sin
 * sueldo, la diferencia de la subrogación y el encargo, la multa, lo que paga
 * una restitución— y no se guardan mientras el mes está abierto.
 *
 * Al entregar el mes a Financiero se congelan aquí, tal como se entregaron. Lo
 * que cambie después en un mes entregado —un reintegro que acorta la licencia,
 * un acto que se anula, un incremento con fecha anterior— se entrega como
 * ajuste en el mes siguiente: la diferencia entre lo que hoy resulta y lo que
 * ya se entregó.
 *
 * Y `subrogaciones.cancelada_en`: la subrogación activa que se cancela deja de
 * surtir efecto ese día, y hasta aquí no quedaba dicho cuándo. Sin la fecha no
 * se sabe cuántos días se pagan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas_novedades_nomina', function (Blueprint $table) {
            $table->id();
            // El mes, AAAA-MM: se entrega una vez.
            $table->string('periodo', 7)->unique();
            $table->foreignId('entregada_por')->constrained('users');
            $table->timestamp('entregada_en');
            $table->unsignedInteger('novedades')->default(0);
            $table->unsignedInteger('ajustes')->default(0);
            // El correo al jefe de Gestión Financiera: a quién y cómo salió.
            $table->string('destinatario')->nullable();
            $table->string('aviso', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('novedades_nomina', function (Blueprint $table) {
            $table->id();
            // Sin cascada: lo entregado no se borra.
            $table->foreignId('entrega_id')->constrained('entregas_novedades_nomina');
            // El mes al que corresponde: el de la entrega, o uno anterior si es
            // un ajuste.
            $table->string('mes_afectado', 7);
            $table->boolean('es_ajuste')->default(false);
            // ¿Sigue en pie después de esta fila? El ajuste que la revierte
            // —el acto se anuló— la deja en false, y no se vuelve a revertir.
            $table->boolean('vigente')->default(true);
            // Identifica la novedad entre una derivación y la siguiente: el
            // concepto y lo que la origina.
            $table->string('clave', 120);
            $table->string('concepto', 40);
            $table->foreignId('servidor_id')->constrained('servidores');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos_servidor');
            $table->foreignId('movimiento_id')->nullable()->constrained('movimientos_personal');
            $table->foreignId('subrogacion_id')->nullable()->constrained('subrogaciones');
            $table->string('acto', 40)->nullable();
            $table->date('desde')->nullable();
            $table->date('hasta')->nullable();
            $table->smallInteger('dias')->nullable();
            $table->decimal('base', 12, 2)->nullable();
            // Positivo, lo que se paga; negativo, lo que se descuenta.
            $table->decimal('monto', 12, 2)->nullable();
            $table->text('detalle');
            $table->timestamps();

            $table->index(['mes_afectado', 'clave']);
            $table->index('servidor_id');
        });

        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->date('cancelada_en')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('subrogaciones', function (Blueprint $table) {
            $table->dropColumn('cancelada_en');
        });

        Schema::dropIfExists('novedades_nomina');
        Schema::dropIfExists('entregas_novedades_nomina');
    }
};
