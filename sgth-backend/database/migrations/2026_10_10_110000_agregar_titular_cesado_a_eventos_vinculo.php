<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La bitácora del vínculo anota también que cesó el titular cuya ausencia
 * cubría un contrato de reemplazo (diseño de Acciones de Personal, fase 2.1).
 */
return new class extends Migration
{
    private const TIPOS = [
        'contrato_registrado',
        'subrogacion_finalizada',
        'subrogacion_cancelada',
        'cambio_puesto',
        'cambio_regimen',
        'egreso',
    ];

    public function up(): void
    {
        $this->reemplazarCheck([...self::TIPOS, 'titular_cesado']);
    }

    public function down(): void
    {
        DB::table('eventos_vinculo')->where('tipo', 'titular_cesado')->delete();

        $this->reemplazarCheck(self::TIPOS);
    }

    /** @param  list<string>  $tipos */
    private function reemplazarCheck(array $tipos): void
    {
        $lista = implode(', ', array_map(fn (string $t) => "'{$t}'", $tipos));

        DB::statement('ALTER TABLE eventos_vinculo DROP CONSTRAINT IF EXISTS eventos_vinculo_tipo_check');
        DB::statement("ALTER TABLE eventos_vinculo ADD CONSTRAINT eventos_vinculo_tipo_check CHECK (tipo IN ({$lista}))");
    }
};
