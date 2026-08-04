<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->string('motivo_fin')->nullable()->after('fecha_fin');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_servidor', function (Blueprint $table) {
            $table->dropColumn('motivo_fin');
        });
    }
};
