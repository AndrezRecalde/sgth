<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoSubrogacion;
use App\Enums\TipoSubrogacion;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\SubrogacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('TEMP: listarActivas incluye la que empieza y termina hoy', function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $user = User::factory()->create();
    $user->assignRole('admin-uath');
    $this->actingAs($user, 'sanctum');

    $unidad = UnidadAdministrativa::create([
        'codigo' => 'U-1', 'nombre' => 'Unidad', 'nivel' => 1, 'estado' => true,
    ]);
    $partida = PartidaPresupuestaria::create([
        'codigo' => '510512', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);
    $puesto = Puesto::create([
        'codigo' => 'P-1', 'unidad_administrativa_id' => $unidad->id,
        'plazas' => 1, 'es_jefe' => true, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Jefe'])->id,
        'partida_presupuestaria_id' => $partida->id,
    ]);

    $crear = function (string $sufijo) use ($unidad) {
        return Servidor::create([
            'cedula' => str_pad($sufijo, 10, '0', STR_PAD_LEFT),
            'nombre' => 'S', 'apellido' => 'A'.$sufijo,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);
    };

    $hoy = now()->toDateString();

    Subrogacion::create([
        'tipo' => TipoSubrogacion::SUBROGACION->value,
        'servidor_subrogante_id' => $crear('11')->id,
        'servidor_subrogado_id'  => $crear('22')->id,
        'unidad_administrativa_id' => $unidad->id,
        'puesto_subrogado_id' => $puesto->id,
        'fecha_inicio' => $hoy,
        'fecha_fin'    => $hoy,
        'motivo' => 'vacaciones',
        'estado' => EstadoSubrogacion::ACTIVA->value,
        'registrado_por' => $user->id,
    ]);

    $activas = app(SubrogacionService::class)->listarActivas();

    expect($activas)->toHaveCount(1);
});
