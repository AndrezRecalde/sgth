<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->enum('dictamen', [
                'apto',
                'apto_con_restricciones',
                'no_apto',
            ])->nullable()->after('ficha_femo_id');
            $table->text('observacion_medica')->nullable()->after('dictamen');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_certificacion_medica', function (Blueprint $table) {
            $table->dropColumn(['dictamen', 'observacion_medica']);
        });
    }
};
