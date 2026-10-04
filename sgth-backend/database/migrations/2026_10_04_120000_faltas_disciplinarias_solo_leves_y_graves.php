<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * El Art. 42 de la LOSEP solo distingue faltas leves y graves; «muy grave» no
 * existe en la ley (decisión de Talento Humano, 2026-10-04). Una sanción ya
 * registrada como muy grave pasa a grave, que es donde la ley pone la
 * suspensión y la destitución, y la columna deja de admitir el tercer valor.
 *
 * No se corrigen aquí las combinaciones gravedad-sanción que hoy serían
 * inválidas (una falta leve suspendida, por ejemplo): son actos ya emitidos, y
 * un acto registrado con error se anula y se vuelve a emitir, no se enmienda.
 * La regla rige desde ahora en DisciplinarioService.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sanciones_disciplinarias')
            ->where('tipo_falta', 'muy_grave')
            ->update(['tipo_falta' => 'grave']);

        DB::statement('ALTER TABLE sanciones_disciplinarias DROP CONSTRAINT IF EXISTS sanciones_disciplinarias_tipo_falta_check');
        DB::statement("ALTER TABLE sanciones_disciplinarias ADD CONSTRAINT sanciones_disciplinarias_tipo_falta_check CHECK (tipo_falta IN ('leve', 'grave'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sanciones_disciplinarias DROP CONSTRAINT IF EXISTS sanciones_disciplinarias_tipo_falta_check');
        DB::statement("ALTER TABLE sanciones_disciplinarias ADD CONSTRAINT sanciones_disciplinarias_tipo_falta_check CHECK (tipo_falta IN ('leve', 'grave', 'muy_grave'))");
    }
};
