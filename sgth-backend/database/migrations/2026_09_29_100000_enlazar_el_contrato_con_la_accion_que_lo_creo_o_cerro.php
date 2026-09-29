<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué acción de personal creó un vínculo, y cuál lo cerró.
 *
 * Hacía falta para poder ANULAR una acción ya registrada: anularla tiene que
 * deshacer lo que hizo, y hasta ahora no había forma de saber qué contrato
 * había nacido de un ingreso concreto ni cuál había cerrado una cesación
 * concreta. El único rastro era el texto de `motivo_fin` —«Renuncia — Acción de
 * Personal #47.»—, que no es algo contra lo que consultar.
 *
 * Las dos son nullable y se quedan así: los contratos de carga inicial no
 * nacieron de ninguna acción, y un contrato vigente no tiene acción de cierre.
 * `nullOnDelete` porque perder la acción no debe llevarse el vínculo por
 * delante; el contrato es el hecho, la acción es el papel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $tabla) {
            $tabla->foreignId('movimiento_origen_id')
                ->nullable()
                ->after('cubre_movimiento_id')
                ->constrained('movimientos_personal')
                ->nullOnDelete();

            $tabla->foreignId('movimiento_cierre_id')
                ->nullable()
                ->after('movimiento_origen_id')
                ->constrained('movimientos_personal')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('movimiento_origen_id');
            $tabla->dropConstrainedForeignId('movimiento_cierre_id');
        });
    }
};
