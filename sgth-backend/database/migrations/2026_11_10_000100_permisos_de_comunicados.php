<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Los permisos de las noticias y comunicados
 * (docs/diseno-noticias-comunicados.md, 7.3 y decisión D1).
 *
 * - `admin-uath` redacta, publica y ve las lecturas.
 * - `asistente-uath` redacta y ve las lecturas. Publicar no va en su rol: se
 *   le da a la persona concreta como permiso adicional desde Usuarios, y se le
 *   quita igual.
 * - `auditor` ve las lecturas.
 * - `admin-ti` recibe todo, de forma explícita, para que la pantalla de
 *   Usuarios muestre lo que el rol puede hacer.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. En una base recién creada los roles aún no
 * existen y no se asigna nada: lo hace el seeder, que ya incluye todo esto.
 */
return new class extends Migration
{
    private const PERMISOS_NUEVOS = [
        'redactar-comunicados', 'publicar-comunicados', 'ver-lecturas-comunicados',
    ];

    private const OTORGADOS = [
        'admin-ti'       => ['redactar-comunicados', 'publicar-comunicados', 'ver-lecturas-comunicados'],
        'admin-uath'     => ['redactar-comunicados', 'publicar-comunicados', 'ver-lecturas-comunicados'],
        'asistente-uath' => ['redactar-comunicados', 'ver-lecturas-comunicados'],
        'auditor'        => ['ver-lecturas-comunicados'],
    ];

    public function up(): void
    {
        foreach (self::PERMISOS_NUEVOS as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'sanctum']);
        }

        foreach (self::OTORGADOS as $nombreRol => $permisos) {
            Role::where('name', $nombreRol)->where('guard_name', 'sanctum')->first()
                ?->givePermissionTo($permisos);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Las asignaciones a roles y los permisos adicionales de cada usuario
        // caen por cascada con el permiso.
        Permission::whereIn('name', self::PERMISOS_NUEVOS)->where('guard_name', 'sanctum')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
