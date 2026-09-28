<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué se anuló una acción de personal.
 *
 * Anular es un acto administrativo sobre otro acto administrativo, y hasta ahora
 * no dejaba constancia de nada: la pantalla preguntaba sí o no con `confirmar()`
 * y el movimiento quedaba en 'anulada' sin una línea que explicara la decisión.
 * En Permisos, Vacaciones y Viáticos el motivo se pide desde hace tiempo con
 * `MotivoModal`; aquí faltaba el sitio donde guardarlo.
 *
 * Nullable a propósito: lo ya anulado no tiene motivo que inventarle, y el
 * requisito se impone desde ahora en `TransicionarMovimientoRequest`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->string('motivo_anulacion', 500)
                ->nullable()
                ->after('fecha_notificacion');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropColumn('motivo_anulacion');
        });
    }
};
