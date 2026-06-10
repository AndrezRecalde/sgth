<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('facturas_viatico', function (Blueprint $table) {
            $table->string('ruc_proveedor', 20)
                  ->nullable()->change();
            $table->text('detalle')
                  ->nullable()->change();
            $table->string('numero_factura', 50)
                  ->nullable()->change();
            $table->string('numero_ticket', 50)
                  ->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas_viatico', function (Blueprint $table) {
            $table->string('ruc_proveedor', 20)
                  ->nullable(false)->change();
        });
    }
};
