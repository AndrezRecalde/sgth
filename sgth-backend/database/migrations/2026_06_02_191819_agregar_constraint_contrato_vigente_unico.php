<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verificar y corregir datos existentes:
        // Si un servidor tiene múltiples contratos vigentes,
        // dejar solo el más reciente como vigente
        \DB::statement("
            UPDATE contratos_servidor
            SET estado = 'terminado'
            WHERE estado = 'vigente'
            AND id NOT IN (
                SELECT MAX(id)
                FROM contratos_servidor
                WHERE estado = 'vigente'
                GROUP BY servidor_id
            )
        ");
    }

    public function down(): void
    {
        // No reversible — es corrección de datos
    }
};
