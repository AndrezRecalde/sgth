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
        Schema::table('tramos_viatico', function (Blueprint $table) {
            $table->string('tipo_tramo', 20)
                  ->default('destino')
                  ->after('orden');
        });
    }

    public function down(): void
    {
        Schema::table('tramos_viatico', function (Blueprint $table) {
            $table->dropColumn('tipo_tramo');
        });
    }
};
