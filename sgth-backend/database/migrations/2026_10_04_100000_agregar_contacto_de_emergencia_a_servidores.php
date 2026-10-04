<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A quién llamar si al servidor le pasa algo en el trabajo (pedido del
 * usuario, 2026-10-04). Opcional: nombre, parentesco y teléfono. El nombre y
 * el teléfono se piden juntos —uno sin el otro no sirve para llamar—; el
 * parentesco es texto libre, porque puede no ser familiar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('contacto_emergencia_nombre', 150)->nullable()->after('direccion_domicilio');
            $table->string('contacto_emergencia_parentesco', 50)->nullable()->after('contacto_emergencia_nombre');
            $table->string('contacto_emergencia_telefono', 20)->nullable()->after('contacto_emergencia_parentesco');
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn([
                'contacto_emergencia_nombre',
                'contacto_emergencia_parentesco',
                'contacto_emergencia_telefono',
            ]);
        });
    }
};
