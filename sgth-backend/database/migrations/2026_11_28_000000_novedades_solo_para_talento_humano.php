<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Las novedades del mes, solo para Talento Humano
 * (docs/plan-roles-y-accesos.md, PR 8; decisión 2 del 2026-10-25).
 *
 * El rol financiero tenía `ver-novedades-nomina`, pero la pantalla vive en el
 * SGTH, al que no entra. No le hace falta: al entregar el mes, Talento Humano
 * le manda las novedades por correo, en Excel, al jefe de Gestión Financiera.
 * Las ven admin-uath (que las entrega), asistente-uath y el auditor.
 *
 * Va en una migración y no solo en `RolPermisoSeeder` porque volver a correr el
 * seeder en producción sincroniza los permisos de cada rol y se llevaría los
 * ajustes hechos desde Usuarios. Un permiso dado a mano a una persona concreta
 * no se toca.
 */
return new class extends Migration
{
    private const PERMISO = 'ver-novedades-nomina';

    public function up(): void
    {
        Role::where('name', 'financiero')->where('guard_name', 'sanctum')->first()
            ?->revokePermissionTo(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'financiero')->where('guard_name', 'sanctum')->first()
            ?->givePermissionTo(self::PERMISO);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
