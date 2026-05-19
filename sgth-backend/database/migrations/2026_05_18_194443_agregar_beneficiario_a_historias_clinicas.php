<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('historias_clinicas', function (Blueprint $table) {
            // Hacer que servidor_id sea nullable
            $table->foreignId('servidor_id')->nullable()->change();
            
            // Agregar beneficiario_id
            $table->foreignId('beneficiario_id')->nullable()->after('servidor_id')->constrained('beneficiarios')->restrictOnDelete();
        });

        // Constraint CHECK en PostgreSQL
        DB::statement("
            ALTER TABLE historias_clinicas
            ADD CONSTRAINT chk_propietario_exclusivo
            CHECK (
                (servidor_id IS NOT NULL AND beneficiario_id IS NULL)
                OR
                (servidor_id IS NULL AND beneficiario_id IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        // Quitar el constraint
        DB::statement("ALTER TABLE historias_clinicas DROP CONSTRAINT IF EXISTS chk_propietario_exclusivo");
        
        Schema::table('historias_clinicas', function (Blueprint $table) {
            $table->dropForeign(['beneficiario_id']);
            $table->dropColumn('beneficiario_id');
            
            // Volver a hacer que servidor_id no sea nulo (esto podría fallar si hay datos con NULL en la DB, pero es un down())
            $table->foreignId('servidor_id')->nullable(false)->change();
        });
    }
};
