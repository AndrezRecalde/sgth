<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El documento de origen de las vacaciones de una autoridad electa (fase 4.1d).
 *
 * Al Prefecto y al Viceprefecto **se las concede el Consejo Provincial por
 * resolución**, dentro de los 60 días del COOTAD Art. 47 s (TH, 2026-11-26).
 * No hay jefe inmediato que las autorice: la resolución es la autorización, y
 * por eso se guarda con la solicitud, como se guarda en la licencia del
 * Consejo y en el cese.
 *
 * Nula en todas las demás: a un servidor las autoriza su jefe, y lo que queda
 * del papel es su folio y la firma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->string('resolucion_numero', 100)->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('vacaciones', function (Blueprint $table) {
            $table->dropColumn('resolucion_numero');
        });
    }
};
