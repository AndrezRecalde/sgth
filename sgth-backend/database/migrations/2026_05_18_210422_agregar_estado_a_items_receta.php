<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items_receta', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'despachado_parcial', 'despachado_completo'])
                  ->default('pendiente')
                  ->after('cantidad_despachada');
        });
    }

    public function down(): void
    {
        Schema::table('items_receta', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
