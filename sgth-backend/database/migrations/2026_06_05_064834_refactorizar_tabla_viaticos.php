<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            // Fecha en que el servidor registra la solicitud
            $table->date('fecha_solicitud')
                  ->default(now()->toDateString())
                  ->after('zona');

            // Fecha y hora real de salida y llegada
            // (calculadas automáticamente desde tramos)
            $table->dateTime('datetime_salida')
                  ->nullable()
                  ->after('fecha_solicitud');

            $table->dateTime('datetime_llegada')
                  ->nullable()
                  ->after('datetime_salida');

            // Total días calculado desde datetime_salida/llegada
            $table->decimal('total_dias', 5, 2)
                  ->default(0)
                  ->after('datetime_llegada');
        });
    }

    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_solicitud',
                'datetime_salida',
                'datetime_llegada',
                'total_dias',
            ]);
        });
    }
};
