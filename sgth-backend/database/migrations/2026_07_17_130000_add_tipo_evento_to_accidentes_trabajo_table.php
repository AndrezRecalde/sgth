<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accidentes_trabajo', function (Blueprint $table) {
            $table->string('tipo_evento', 20)->default('accidente')->after('servidor_id');
            $table->index('tipo_evento');
        });
    }

    public function down(): void
    {
        Schema::table('accidentes_trabajo', function (Blueprint $table) {
            $table->dropColumn('tipo_evento');
        });
    }
};
