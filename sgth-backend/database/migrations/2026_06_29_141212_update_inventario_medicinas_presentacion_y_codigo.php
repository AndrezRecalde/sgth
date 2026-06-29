<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Cambiar presentacion de string a enum
        DB::statement("
            ALTER TABLE inventario_medicinas
            ALTER COLUMN presentacion TYPE VARCHAR(50)
        ");

        DB::statement("
            ALTER TABLE inventario_medicinas
            ADD CONSTRAINT check_presentacion_valida 
            CHECK (presentacion IN ('tableta','capsula','jarabe','gotas','inyectable','crema','supositorio','spray','parche','solucion','polvo','otro'))
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE inventario_medicinas
            DROP CONSTRAINT check_presentacion_valida
        ");
    }
};
