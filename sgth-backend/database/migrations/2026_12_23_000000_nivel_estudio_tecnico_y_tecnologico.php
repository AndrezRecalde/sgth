<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| El historial académico admite el técnico superior y el tecnológico superior
| (docs/diseno-manual-de-puestos.md, P2). La columna nació con `enum()`, que en
| PostgreSQL es un varchar con un CHECK de sus cuatro valores: se cambia el
| CHECK, no el tipo.
*/
return new class extends Migration
{
    private const NIVELES_ANTES = ['primaria', 'secundaria', 'tercer_nivel', 'cuarto_nivel'];

    private const NIVELES_AHORA = [
        'primaria', 'secundaria', 'tecnico_superior', 'tecnologico_superior', 'tercer_nivel', 'cuarto_nivel',
    ];

    public function up(): void
    {
        $this->reemplazarCheck(self::NIVELES_AHORA);
    }

    public function down(): void
    {
        // Volver atrás no decide por nadie a qué nivel bajar un título.
        $conNivelNuevo = DB::table('historial_academico_servidor')
            ->whereIn('nivel_estudio', ['tecnico_superior', 'tecnologico_superior'])
            ->count();

        if ($conNivelNuevo > 0) {
            throw new RuntimeException(
                "{$conNivelNuevo} títulos son de técnico o tecnológico superior: corríjalos antes de revertir."
            );
        }

        $this->reemplazarCheck(self::NIVELES_ANTES);
    }

    /** @param list<string> $niveles */
    private function reemplazarCheck(array $niveles): void
    {
        $lista = implode(', ', array_map(fn (string $n) => "'{$n}'", $niveles));

        DB::statement('ALTER TABLE historial_academico_servidor DROP CONSTRAINT IF EXISTS historial_academico_servidor_nivel_estudio_check');
        DB::statement("ALTER TABLE historial_academico_servidor ADD CONSTRAINT historial_academico_servidor_nivel_estudio_check CHECK (nivel_estudio IN ({$lista}))");
    }
};
