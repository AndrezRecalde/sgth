<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->decimal('coeficiente_exterior', 5, 4)
                  ->nullable()
                  ->after('pais_destino');
            $table->string('motivo_rechazo')->nullable()
                  ->after('coeficiente_exterior');
        });
    }

    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropColumn([
                'coeficiente_exterior',
                'motivo_rechazo',
            ]);
        });
    }
};
