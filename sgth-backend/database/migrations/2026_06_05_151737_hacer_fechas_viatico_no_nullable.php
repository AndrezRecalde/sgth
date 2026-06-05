<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Para registros existentes sin fechas,
        // asignar valores temporales
        DB::statement("
            UPDATE viaticos
            SET datetime_salida  = created_at,
                datetime_llegada = created_at + INTERVAL '1 day'
            WHERE datetime_salida IS NULL
        ");

        Schema::table('viaticos', function (Blueprint $table) {
            $table->dateTime('datetime_salida')
                  ->nullable(false)
                  ->change();
            $table->dateTime('datetime_llegada')
                  ->nullable(false)
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('viaticos', function (Blueprint $table) {
            $table->dateTime('datetime_salida')
                  ->nullable()
                  ->change();
            $table->dateTime('datetime_llegada')
                  ->nullable()
                  ->change();
        });
    }
};
