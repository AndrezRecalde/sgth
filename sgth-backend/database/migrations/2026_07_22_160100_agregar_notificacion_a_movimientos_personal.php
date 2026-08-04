<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->foreignId('notificado_por')->nullable()
                ->after('fecha_registro')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('fecha_notificacion')->nullable()->after('notificado_por');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_personal', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notificado_por');
            $table->dropColumn('fecha_notificacion');
        });
    }
};
