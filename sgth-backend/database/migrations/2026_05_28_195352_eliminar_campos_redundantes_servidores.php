<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_inicio_ultimo_contrato',
                'fecha_fin_ultimo_contrato',
                'provincia_domicilio',
                'ciudad_domicilio',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('servidores', function (Blueprint $table) {
            $table->string('provincia_domicilio', 100)->nullable();
            $table->string('ciudad_domicilio', 100)->nullable();
            $table->date('fecha_inicio_ultimo_contrato')->nullable();
            $table->date('fecha_fin_ultimo_contrato')->nullable();
        });
    }
};
