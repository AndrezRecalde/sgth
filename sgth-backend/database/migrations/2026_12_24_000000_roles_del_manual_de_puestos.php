<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Los roles del Manual de Puestos (docs/diseno-manual-de-puestos.md, P2; TH,
| D3, 2026-11-10).
|
| - Los roles admiten `direccion_unidad` y `ejecucion_supervision`, en los
|   puestos y en los grupos ocupacionales. Las dos columnas nacieron con
|   `enum()`, que en PostgreSQL es un CHECK de sus valores: se cambia el CHECK.
| - El rol que sugiere cada grupo pasa a ser el que más usa el manual en su
|   grado. Solo si el grupo todavía tiene el de antes: si Talento Humano ya lo
|   cambió, se respeta.
|
| Los puestos que ya existen conservan su rol: lo que se aprobó es el rol que
| sugiere el grupo, y el de cada puesto se cambia uno por uno.
*/
return new class extends Migration
{
    private const ROLES_ANTES = [
        'dignatario', 'ejecucion_coordinacion', 'ejecucion_procesos', 'ejecucion_procesos_apoyo',
        'administrativo', 'codigo_trabajo',
    ];

    private const ROLES_AHORA = [
        'dignatario', 'direccion_unidad', 'ejecucion_coordinacion', 'ejecucion_supervision',
        'ejecucion_procesos', 'ejecucion_procesos_apoyo', 'administrativo', 'codigo_trabajo',
    ];

    /** Grado del catálogo => [rol que sugería, rol que sugiere]. */
    private const SUGERIDOS = [
        // De apoyo (grados 3 a 6): el manual usa «apoyo y tecnológico» en 8 de 13 fichas.
        'SPA1'  => ['administrativo', 'ejecucion_procesos_apoyo'],
        'SPA2'  => ['administrativo', 'ejecucion_procesos_apoyo'],
        'SPA3'  => ['administrativo', 'ejecucion_procesos_apoyo'],
        'SPA4'  => ['administrativo', 'ejecucion_procesos_apoyo'],
        // Grados 12 y 13: «supervisión» en 27 de 40 fichas.
        'SP6'   => ['ejecucion_procesos', 'ejecucion_supervision'],
        'SP7'   => ['ejecucion_procesos', 'ejecucion_supervision'],
        // El director: «dirección de unidad» en 13 de sus 15 fichas. NJS-6
        // junta coordinadores, procurador y secretario general, que el manual
        // reparte entre los dos roles: se queda como está.
        'NJS-5' => ['ejecucion_coordinacion', 'direccion_unidad'],
    ];

    public function up(): void
    {
        $this->reemplazarChecks(self::ROLES_AHORA);

        foreach (self::SUGERIDOS as $grado => [$antes, $ahora]) {
            DB::table('grupos_ocupacionales')
                ->where('grado_codigo', $grado)
                ->where('rol_puesto', $antes)
                ->update(['rol_puesto' => $ahora]);
        }
    }

    public function down(): void
    {
        foreach (self::SUGERIDOS as $grado => [$antes, $ahora]) {
            DB::table('grupos_ocupacionales')
                ->where('grado_codigo', $grado)
                ->where('rol_puesto', $ahora)
                ->update(['rol_puesto' => $antes]);
        }

        // Lo que se haya marcado con los roles nuevos vuelve al más cercano de
        // antes: la supervisión es ejecución, y la dirección, coordinación.
        foreach (['puestos', 'grupos_ocupacionales'] as $tabla) {
            DB::table($tabla)->where('rol_puesto', 'ejecucion_supervision')->update(['rol_puesto' => 'ejecucion_procesos']);
            DB::table($tabla)->where('rol_puesto', 'direccion_unidad')->update(['rol_puesto' => 'ejecucion_coordinacion']);
        }

        $this->reemplazarChecks(self::ROLES_ANTES);
    }

    /** @param list<string> $roles */
    private function reemplazarChecks(array $roles): void
    {
        $lista = implode(', ', array_map(fn (string $r) => "'{$r}'", $roles));

        foreach (['puestos', 'grupos_ocupacionales'] as $tabla) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT IF EXISTS {$tabla}_rol_puesto_check");
            DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$tabla}_rol_puesto_check CHECK (rol_puesto IN ({$lista}))");
        }
    }
};
