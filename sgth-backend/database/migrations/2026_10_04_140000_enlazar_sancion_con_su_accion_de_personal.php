<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * La acción de personal que nace de una sanción (2026-10-04): la de
 * «Régimen Disciplinario — Sanción disciplinaria» para la multa y la
 * suspensión, que es el documento con el que Financiero aplica el descuento,
 * y la cesación de funciones de una destitución, que ya se creaba pero sin
 * enlace. Es el mismo enlace que `vistos_buenos.movimiento_personal_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->foreignId('movimiento_personal_id')
                ->nullable()
                ->after('sumario_id')
                ->constrained('movimientos_personal')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sanciones_disciplinarias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_personal_id');
        });
    }
};
