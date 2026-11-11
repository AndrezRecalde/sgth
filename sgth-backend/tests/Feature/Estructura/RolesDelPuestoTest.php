<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Enums\RolPuesto;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Los roles del Manual de Puestos (docs/diseno-manual-de-puestos.md, P2): TH
| aprobó el 2026-11-10 (D3) agregar «Dirección de unidad» y «Ejecución y
| supervisión», llamar al de apoyo como el manual, y que cada grupo ocupacional
| sugiera el rol que más usa el manual en su grado.
*/
beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    foreach ([Permiso::GESTIONAR_PUESTOS, Permiso::VER_ESTRUCTURA] as $permiso) {
        $rol->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);
    $this->actingAs($usuario, 'sanctum');

    $this->grupo = fn (string $codigo, string $rol) => GrupoOcupacional::create([
        'grado_codigo' => $codigo, 'grupo' => "Grupo {$codigo}", 'rmu' => 1000,
        'regimen' => 'losep', 'activo' => true, 'rol_puesto' => $rol,
    ]);
});

test('un puesto admite los dos roles nuevos, y el de apoyo se llama como en el manual', function () {
    $unidad = unidadDePrueba(['nombre' => 'Dirección de los Roles']);

    foreach ([RolPuesto::DIRECCION_UNIDAD, RolPuesto::EJECUCION_SUPERVISION] as $rol) {
        $this->postJson('/api/v1/estructura/puestos', [
            'cargo_id' => Cargo::firstOrCreate(['nombre' => "Cargo {$rol->value}"])->id,
            'unidad_administrativa_id' => $unidad->id, 'regimen_laboral' => 'losep',
            'plazas' => 1, 'rol_puesto' => $rol->value,
        ])->assertCreated()->assertJsonPath('datos.rol_puesto', $rol->value);
    }

    expect(RolPuesto::EJECUCION_PROCESOS_APOYO->etiqueta())->toBe('Ejecución de Procesos de Apoyo y Tecnológico');
});

test('la migración cambia el rol que sugiere el grupo solo si todavía tiene el de antes', function () {
    $sp6 = ($this->grupo)('SP6', 'ejecucion_procesos');
    $spa2 = ($this->grupo)('SPA2', 'administrativo');
    $director = ($this->grupo)('NJS-5', 'ejecucion_coordinacion');
    // Talento Humano ya le había puesto otro: se respeta.
    $sp7 = ($this->grupo)('SP7', 'ejecucion_coordinacion');
    // NJS-6 junta coordinadores, procurador y secretario general: no cambia.
    $njs6 = ($this->grupo)('NJS-6', 'ejecucion_coordinacion');

    $migracion = require database_path('migrations/2026_12_24_000000_roles_del_manual_de_puestos.php');
    $migracion->up();

    expect($sp6->fresh()->rol_puesto)->toBe(RolPuesto::EJECUCION_SUPERVISION)
        ->and($spa2->fresh()->rol_puesto)->toBe(RolPuesto::EJECUCION_PROCESOS_APOYO)
        ->and($director->fresh()->rol_puesto)->toBe(RolPuesto::DIRECCION_UNIDAD)
        ->and($sp7->fresh()->rol_puesto)->toBe(RolPuesto::EJECUCION_COORDINACION)
        ->and($njs6->fresh()->rol_puesto)->toBe(RolPuesto::EJECUCION_COORDINACION);

    // Volver atrás deja cada grupo como estaba.
    $migracion->down();
    expect($sp6->fresh()->rol_puesto)->toBe(RolPuesto::EJECUCION_PROCESOS)
        ->and($director->fresh()->rol_puesto)->toBe(RolPuesto::EJECUCION_COORDINACION)
        ->and($spa2->fresh()->rol_puesto)->toBe(RolPuesto::ADMINISTRATIVO);

    $migracion->up();
});
