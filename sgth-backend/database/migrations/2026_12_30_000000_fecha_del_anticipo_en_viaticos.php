<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo salió el anticipo.
 *
 * El comprobante contable descontaba el anticipo sin decir cuándo se entregó
 * ni con qué documento, y había que buscarlo en otra parte. El número ya se
 * guardaba —es el que Financiero escribe al entregarlo—, pero la fecha no se
 * registraba en ningún campo. Pedido por Gestión Financiera (2026-11-23, A2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->date('fecha_anticipo')
                  ->nullable()
                  ->after('monto_anticipo')
                  ->comment('Fecha en que Financiero entregó el anticipo.');
        });
    }

    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropColumn('fecha_anticipo');
        });
    }
};
