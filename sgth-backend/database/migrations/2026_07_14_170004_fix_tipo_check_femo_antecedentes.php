<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE femo_antecedentes DROP CONSTRAINT IF EXISTS femo_antecedentes_tipo_check');
        DB::statement("ALTER TABLE femo_antecedentes ADD CONSTRAINT femo_antecedentes_tipo_check CHECK (
            (tipo)::text = ANY ((ARRAY[
                'clinico', 'quirurgico', 'familiar', 'ginecologico',
                'reproductivo_masculino', 'transfusion', 'tratamiento_hormonal', 'otro'
            ])::text[])
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE femo_antecedentes DROP CONSTRAINT IF EXISTS femo_antecedentes_tipo_check');
        DB::statement("ALTER TABLE femo_antecedentes ADD CONSTRAINT femo_antecedentes_tipo_check CHECK (
            (tipo)::text = ANY ((ARRAY[
                'clinico', 'quirurgico', 'familiar',
                'ginecologico', 'reproductivo_masculino', 'otro'
            ])::text[])
        )");
    }
};
