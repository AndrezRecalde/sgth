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
        Schema::create('cuentas_bancarias_servidor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('servidor_id')->constrained('servidores')->cascadeOnDelete();
            $table->foreignId('entidad_financiera_id')->constrained('entidades_financieras')->restrictOnDelete();
            $table->string('nombre_banco_otro')->nullable();
            $table->enum('tipo_cuenta', ['ahorros', 'corriente']);
            $table->string('numero_cuenta');
            $table->enum('proposito', ['sueldo', 'viaticos', 'ambos']);
            $table->boolean('es_principal_sueldo')->default(false);
            $table->boolean('es_principal_viatico')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias_servidor');
    }
};
