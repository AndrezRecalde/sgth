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
        Schema::table('viaticos', function (Blueprint $table) {
            $table->string('codigo_viatico', 20)->unique()->nullable()->after('id');
            $table->foreignId('comision_id')->nullable()->after('codigo_viatico')->constrained('comisiones');
            $table->dropColumn('destino');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->string('destino')->nullable();
            $table->dropForeign(['comision_id']);
            $table->dropColumn(['codigo_viatico', 'comision_id']);
        });
    }
};
