<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de certificados laborales emitidos.
 *
 * El certificado no es un acto administrativo —no crea nada, constata lo que
 * el expediente ya dice—, así que no tiene estados ni pasa por aprobación. Lo
 * que sí necesita es ser comprobable: un PDF lo edita cualquiera en dos
 * minutos, y el banco o el IESS que lo reciben no tienen forma de saber si
 * salió de aquí.
 *
 * De ahí esta tabla: cada emisión deja un código con el que se puede
 * verificar, y guarda lo que el documento decía ese día. El contenido cambia
 * con el tiempo —un vínculo nuevo, un cambio de unidad—, así que sin la foto
 * no se podría confirmar un certificado de hace seis meses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emisiones_certificado_laboral', function (Blueprint $table) {
            $table->id();

            // Se imprime en el pie y viaja en la dirección de verificación.
            // Aleatorio y no correlativo: con AP-2026-0004 cualquiera adivina
            // que existe AP-2026-0005 y va probando.
            $table->string('codigo', 32)->unique();

            $table->foreignId('servidor_id')->constrained('servidores')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->boolean('con_remuneracion')->default(false);

            $table->foreignId('emitido_por')->constrained('users');
            $table->timestamp('emitido_en');
            // 30 días, según acordó la UATH el 2026-09-25. Se guarda en vez de
            // derivarse porque es parte de lo que el papel afirma: si mañana
            // cambia el plazo, los ya emitidos siguen diciendo lo suyo.
            $table->date('vence_en');

            // Sellados al emitir, como en la Acción de Personal: quien firmó un
            // certificado de 2024 debe seguir apareciendo aunque hoy el cargo
            // lo ocupe otra persona.
            $table->string('firmante_nombre')->nullable();
            $table->string('firmante_cargo')->nullable();
            $table->string('firmante_cedula', 13)->nullable();

            // Lo que el documento decía ese día: nombres, cédula, régimen,
            // tiempo de servicio, puesto y unidad, y los períodos listados.
            $table->json('datos');

            $table->timestamps();

            $table->index('servidor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emisiones_certificado_laboral');
    }
};
