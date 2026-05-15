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
        Schema::table('servidores', function (Blueprint $table) {
            $table->foreignId('provincia_nacimiento_id')->nullable()->after('pais_origen')->constrained('provincias');
            $table->foreignId('ciudad_nacimiento_id')->nullable()->after('provincia_nacimiento_id')->constrained('ciudades');
            
            $table->dropColumn(['provincia_nacimiento', 'ciudad_nacimiento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('provincia_nacimiento')->nullable()->after('pais_origen');
            $table->string('ciudad_nacimiento')->nullable()->after('provincia_nacimiento');
            
            $table->dropForeign(['provincia_nacimiento_id']);
            $table->dropForeign(['ciudad_nacimiento_id']);
            
            $table->dropColumn(['provincia_nacimiento_id', 'ciudad_nacimiento_id']);
        });
    }
};
