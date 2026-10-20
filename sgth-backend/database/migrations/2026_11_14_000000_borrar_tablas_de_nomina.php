<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Se borran las tablas de la nómina del SGTH, que no calcula la nómina desde
 * el 2026-10-20 (decisión de Talento Humano y Financiero; el código se fue en
 * el #491): los roles de pago, su detalle, los descuentos recurrentes, el
 * traspaso al ERP, las nóminas y su catálogo de conceptos. Y el permiso de ver
 * el rol de pago propio, del portal, que ya no existe.
 *
 * Borrar no se deshace. Si las tablas de cálculo tienen filas, la migración se
 * niega y pide respaldarlas (`pg_dump`) y volver a correrla con
 * SGTH_BORRAR_NOMINA=1 en el entorno. El catálogo de conceptos no cuenta: es
 * lo que sembraron las migraciones.
 *
 * Los archivos que pudieran haber quedado en el disco —el XML del traspaso al
 * ERP, los PDF de los roles— no se tocan: se borran a mano si se quiere.
 *
 * El `down()` vuelve a crear las tablas vacías, con su forma final, corriendo
 * sus migraciones originales en orden: vuelve la estructura, no los datos.
 */
return new class extends Migration
{
    /** Las que guardan cálculos: con filas, no se borran sin confirmar. */
    private const DE_CALCULO = ['detalle_nomina', 'roles_pago', 'descuentos_recurrentes', 'handoffs_erp', 'nominas'];

    /** En el orden que piden sus llaves foráneas. */
    private const EN_ORDEN = ['detalle_nomina', 'roles_pago', 'descuentos_recurrentes', 'handoffs_erp', 'nominas', 'conceptos_nomina'];

    private const PERMISO = 'ver-rol-pago-propio';

    public function up(): void
    {
        $conFilas = collect(self::DE_CALCULO)
            ->filter(fn (string $tabla) => Schema::hasTable($tabla))
            ->mapWithKeys(fn (string $tabla) => [$tabla => DB::table($tabla)->count()])
            ->filter();

        if ($conFilas->isNotEmpty() && ! $this->confirmado()) {
            throw new RuntimeException(
                'Las tablas de la nómina tienen datos ('
                    .$conFilas->map(fn (int $n, string $tabla) => "{$tabla}: {$n}")->implode(', ')
                    .'). Respáldelas (pg_dump -t '.implode(' -t ', self::EN_ORDEN).') y vuelva a correr la '
                    .'migración con SGTH_BORRAR_NOMINA=1 en el entorno.'
            );
        }

        foreach (self::EN_ORDEN as $tabla) {
            Schema::dropIfExists($tabla);
        }

        // Quitarlo de los roles y de los usuarios lo hace la base, en cascada.
        Permission::where('name', self::PERMISO)->where('guard_name', 'sanctum')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ([
            '2026_05_12_155011_crear_tabla_nominas',
            '2026_05_12_155218_crear_tabla_conceptos_nomina',
            '2026_05_12_155314_crear_tabla_detalle_nomina',
            '2026_05_12_155405_crear_tabla_roles_pago',
            '2026_05_12_155631_crear_tabla_handoffs_erp',
            '2026_05_12_170702_crear_tabla_descuentos_recurrentes',
            // Las columnas que les sumó la fase 7.3c, con sus conceptos.
            '2026_10_13_200000_nomina_sobre_el_vinculo',
        ] as $migracion) {
            (require database_path("migrations/{$migracion}.php"))->up();
        }

        // Lo daba a todos los roles la lista base del seeder.
        $permiso = Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'sanctum']);
        Role::where('guard_name', 'sanctum')->get()->each(fn (Role $rol) => $rol->givePermissionTo($permiso));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * SGTH_BORRAR_NOMINA, del entorno del proceso o del `.env`: con la
     * configuración en caché, `env()` ya no lee el `.env`.
     */
    private function confirmado(): bool
    {
        $valor = getenv('SGTH_BORRAR_NOMINA');

        return filter_var($valor !== false ? $valor : env('SGTH_BORRAR_NOMINA', false), FILTER_VALIDATE_BOOL);
    }
};
