<?php

/*
| Quién puede ver, registrar y mover un viático.
|
| Solo seis acciones pedían permiso. Cualquier usuario autenticado listaba los
| viáticos de todos, cambiaba el monto de uno ajeno, lo creaba a nombre de otro,
| lo cancelaba, lo liquidaba, aprobaba vuelos y descargaba los PDF.
|
| La regla, decidida con el usuario: el servidor gestiona lo suyo (y ve aquello
| en lo que va de acompañante); Financiero opera; Talento Humano consulta.
|
| Ningún usuario de estos tests es admin-ti: su `Gate::before` se salta la
| policy y no probaría nada.
*/

use App\Enums\EstadoViatico;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Models\Viatico\AutorizacionVuelo;
use App\Models\Viatico\CatalogoTransporte;
use App\Models\Viatico\EmpresaTransporte;
use App\Models\Viatico\LiquidacionViatico;
use App\Models\Viatico\TramoViatico;
use App\Models\Viatico\Viatico;
use App\Models\Viatico\ViaticoServidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['codigo' => 'GFIN']);
    $cedula = 800005000;

    $this->servidor = function () use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Vera',
            'apellido'                 => 'Viaje',
            'puesto_id'                => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };

    $this->usuario = function (string $rol, ?Servidor $servidor = null) {
        $usuario = User::factory()->create(['servidor_id' => ($servidor ?? ($this->servidor)())->id]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->titular = ($this->servidor)();
    $this->acompanante = ($this->servidor)();

    $this->deTitular = ($this->usuario)('servidor', $this->titular);
    $this->deAcompanante = ($this->usuario)('servidor', $this->acompanante);
    $this->ajeno = ($this->usuario)('servidor');
    $this->financiero = ($this->usuario)('financiero');
    $this->adminUath = ($this->usuario)('admin-uath');
    $this->asistenteUath = ($this->usuario)('asistente-uath');

    DB::table('tarifas_viatico')->insert([
        ['zona' => 'dentro_provincia', 'nivel' => 'servidor', 'tipo_tarifa' => 'con_pernocte', 'valor_diario' => 80],
        ['zona' => 'dentro_provincia', 'nivel' => 'servidor', 'tipo_tarifa' => 'subsistencia', 'valor_diario' => 40],
    ]);

    $this->viatico = Viatico::create([
        'servidor_id'        => $this->titular->id,
        'zona'               => 'dentro_provincia',
        'datetime_salida'    => '2026-10-05 08:00:00',
        'datetime_llegada'   => '2026-10-07 18:00:00',
        'noches'             => 3,
        'justificacion'      => 'Supervisión de obras viales',
        'estado'             => EstadoViatico::SOLICITADO,
        'monto_calculado'    => 240,
        'monto_anticipo'     => 0,
        'modalidad_anticipo' => 'total',
    ]);
    ViaticoServidor::create(['viatico_id' => $this->viatico->id, 'servidor_id' => $this->titular->id, 'es_titular' => true]);
    ViaticoServidor::create(['viatico_id' => $this->viatico->id, 'servidor_id' => $this->acompanante->id, 'es_titular' => false]);

    $this->solicitud = [
        'zona'               => 'dentro_provincia',
        'justificacion'      => 'Capacitación en el GAD de Quinindé',
        'modalidad_anticipo' => 'total',
        'datetime_salida'    => '2026-11-03 08:00:00',
        'datetime_llegada'   => '2026-11-04 18:00:00',
    ];

    $this->en = function (EstadoViatico $estado) {
        $this->viatico->update(['estado' => $estado]);
    };

    // Aprobar exige al menos un tramo.
    $this->conItinerario = function () {
        $catalogo = CatalogoTransporte::firstOrCreate(['codigo' => 'BUS'], ['nombre' => 'Bus', 'tipo_vehiculo' => 'terrestre']);
        $empresa = EmpresaTransporte::firstOrCreate(['codigo' => 'TE'], ['catalogo_transporte_id' => $catalogo->id, 'nombre' => 'Trans Esmeraldas']);

        return TramoViatico::create([
            'viatico_id' => $this->viatico->id,
            'origen_tipo' => 'nacional', 'origen_ciudad' => 'Esmeraldas',
            'destino_tipo' => 'nacional', 'destino_ciudad' => 'Quinindé',
            'empresa_transporte_id' => $empresa->id,
            'datetime_salida' => '2026-10-05 08:00:00', 'datetime_llegada' => '2026-10-05 11:00:00',
        ]);
    };
});

// ── Ver ──────────────────────────────────────────────────────────────

it('el servidor lista solo los viáticos en los que viaja', function () {
    Viatico::create(array_merge($this->viatico->only([
        'zona', 'datetime_salida', 'datetime_llegada', 'justificacion', 'estado',
    ]), ['servidor_id' => $this->ajeno->servidor_id]));

    $ids = fn (User $quien) => collect(
        $this->actingAs($quien, 'sanctum')->getJson('/api/v1/viaticos')->assertOk()->json('datos.data')
    )->pluck('id');

    expect($ids($this->deTitular))->toEqual(collect([$this->viatico->id]))
        ->and($ids($this->deAcompanante))->toEqual(collect([$this->viatico->id]))
        ->and($ids($this->ajeno))->toHaveCount(1)->not->toContain($this->viatico->id)
        ->and($ids($this->financiero))->toHaveCount(2)
        ->and($ids($this->adminUath))->toHaveCount(2);
});

it('el filtro servidor_id no abre los viáticos de otro', function () {
    $this->actingAs($this->ajeno, 'sanctum')
        ->getJson("/api/v1/viaticos?servidor_id={$this->titular->id}")
        ->assertOk()
        ->assertJsonCount(0, 'datos.data');
});

it('el detalle, los tramos y el PDF solo los ve quien viaja o consulta', function (string $quien, int $estado) {
    $usuario = $this->{$quien};

    $this->actingAs($usuario, 'sanctum')->getJson("/api/v1/viaticos/{$this->viatico->codigo_viatico}")->assertStatus($estado);
    $this->actingAs($usuario, 'sanctum')->getJson("/api/v1/viaticos/{$this->viatico->id}/tramos")->assertStatus($estado);
    $this->actingAs($usuario, 'sanctum')->get("/api/v1/viaticos/{$this->viatico->codigo_viatico}/solicitud/generar-enlace")->assertStatus($estado);
})->with([
    'titular'     => ['deTitular', 200],
    'acompañante' => ['deAcompanante', 200],
    'Financiero'  => ['financiero', 200],
    'Talento Humano' => ['adminUath', 200],
    'otro servidor'  => ['ajeno', 403],
]);

// ── Registrar ────────────────────────────────────────────────────────

it('a nombre de otro servidor solo registra quien opera los viáticos', function () {
    $url = "/api/v1/viaticos/servidor/{$this->titular->id}/solicitar";

    $this->actingAs($this->ajeno, 'sanctum')->postJson($url, $this->solicitud)->assertForbidden();
    // Se autoriza antes de validar: quien no puede, no recibe la lista de campos.
    $this->actingAs($this->ajeno, 'sanctum')->postJson($url, [])->assertForbidden();
    $this->actingAs($this->adminUath, 'sanctum')->postJson($url, $this->solicitud)->assertForbidden();
    $this->actingAs($this->financiero, 'sanctum')->postJson($url, $this->solicitud)
        ->assertCreated()
        ->assertJsonPath('datos.servidor_id', $this->titular->id);
});

it('ya no existe la ruta que tomaba el id del viático por el del servidor', function () {
    $this->actingAs($this->financiero, 'sanctum')
        ->postJson("/api/v1/viaticos/{$this->viatico->id}/solicitar", $this->solicitud)
        ->assertNotFound();
});

it('el servidor no fija el monto al solicitar', function () {
    $this->actingAs($this->deTitular, 'sanctum')
        ->postJson('/api/v1/viaticos', array_merge($this->solicitud, [
            'zona' => 'exterior', 'tipo_viaje' => 'capacitacion',
            'pais_destino' => 'Colombia', 'monto_calculado' => 5000,
        ]))
        ->assertCreated()
        ->assertJsonPath('datos.monto_calculado', '0.00');
});

// ── Editar ───────────────────────────────────────────────────────────

it('los datos los edita el titular o quien opera, no el acompañante ni otro', function (string $quien, int $estado) {
    $this->actingAs($this->{$quien}, 'sanctum')
        ->patchJson("/api/v1/viaticos/{$this->viatico->id}", ['justificacion' => 'Supervisión de obras y reunión técnica'])
        ->assertStatus($estado);
})->with([
    'titular'        => ['deTitular', 200],
    'Financiero'     => ['financiero', 200],
    'acompañante'    => ['deAcompanante', 403],
    'otro servidor'  => ['ajeno', 403],
    'Talento Humano' => ['adminUath', 403],
]);

it('el monto solo lo cambia quien opera los viáticos', function () {
    $url = "/api/v1/viaticos/{$this->viatico->id}";

    $this->actingAs($this->deTitular, 'sanctum')->patchJson($url, ['monto_calculado' => 9999])->assertForbidden();
    expect((float) $this->viatico->fresh()->monto_calculado)->toBe(240.0);

    $this->actingAs($this->financiero, 'sanctum')->patchJson($url, ['monto_calculado' => 260])->assertOk();
    expect((float) $this->viatico->fresh()->monto_calculado)->toBe(260.0);
});

it('un monto nulo del formulario no se toma como un cambio de monto', function () {
    $this->actingAs($this->deTitular, 'sanctum')
        ->patchJson("/api/v1/viaticos/{$this->viatico->id}", [
            'justificacion'   => 'Supervisión de obras viales en Muisne',
            'monto_calculado' => null,
        ])
        ->assertOk();

    expect((float) $this->viatico->fresh()->monto_calculado)->toBe(240.0);
});

it('el itinerario de otro no se toca', function () {
    $catalogo = CatalogoTransporte::create(['nombre' => 'Bus', 'codigo' => 'BUS', 'tipo_vehiculo' => 'terrestre']);
    $empresa = EmpresaTransporte::create(['catalogo_transporte_id' => $catalogo->id, 'nombre' => 'Trans Esmeraldas', 'codigo' => 'TE']);
    $tramo = [
        'origen_tipo' => 'nacional', 'origen_ciudad' => 'Esmeraldas',
        'destino_tipo' => 'nacional', 'destino_ciudad' => 'Quinindé',
        'empresa_transporte_id' => $empresa->id,
        'datetime_salida' => '2026-10-05 08:00:00', 'datetime_llegada' => '2026-10-05 11:00:00',
    ];
    $url = "/api/v1/viaticos/{$this->viatico->id}/tramos";

    $this->actingAs($this->ajeno, 'sanctum')->postJson($url, $tramo)->assertForbidden();
    $creado = $this->actingAs($this->deTitular, 'sanctum')->postJson($url, $tramo)->assertCreated()->json('datos.id');

    $this->actingAs($this->ajeno, 'sanctum')->putJson("{$url}/{$creado}", ['destino_ciudad' => 'Atacames'])->assertForbidden();
    $this->actingAs($this->deAcompanante, 'sanctum')->deleteJson("{$url}/{$creado}")->assertForbidden();
    expect(TramoViatico::find($creado))->not->toBeNull();
});

it('la solicitud la cancela el titular o quien opera', function () {
    $url = "/api/v1/viaticos/{$this->viatico->id}/cancelar";

    $this->actingAs($this->ajeno, 'sanctum')->postJson($url)->assertForbidden();
    $this->actingAs($this->deAcompanante, 'sanctum')->postJson($url)->assertForbidden();
    $this->actingAs($this->deTitular, 'sanctum')->postJson($url)->assertOk();

    expect($this->viatico->fresh()->estado)->toBe(EstadoViatico::CANCELADO);
});

// ── Liquidación ──────────────────────────────────────────────────────

it('la liquidación de otro no se lee ni se llena ni se confirma', function () {
    ($this->en)(EstadoViatico::PENDIENTE_LIQUIDACION);
    $base = "/api/v1/viaticos/{$this->viatico->id}/liquidacion";

    $this->actingAs($this->ajeno, 'sanctum')->getJson($base)->assertForbidden();
    $this->actingAs($this->ajeno, 'sanctum')->getJson("{$base}/actividades")->assertForbidden();
    $this->actingAs($this->ajeno, 'sanctum')->getJson("{$base}/facturas")->assertForbidden();
    $this->actingAs($this->ajeno, 'sanctum')->postJson("{$base}/actividades", ['actividades' => []])->assertForbidden();
    $this->actingAs($this->ajeno, 'sanctum')->postJson("{$base}/facturas", ['facturas' => []])->assertForbidden();
    $this->actingAs($this->ajeno, 'sanctum')->postJson("{$base}/confirmar")->assertForbidden();
    $this->actingAs($this->deAcompanante, 'sanctum')->postJson("{$base}/confirmar")->assertForbidden();
    $this->actingAs($this->ajeno, 'sanctum')
        ->postJson("/api/v1/viaticos/{$this->viatico->id}/liquidar", ['facturas' => [['monto' => 'x']]])
        ->assertForbidden();

    expect(LiquidacionViatico::count())->toBe(0);
});

it('quien solo consulta abre la liquidación sin crearla', function () {
    ($this->en)(EstadoViatico::PENDIENTE_LIQUIDACION);

    $this->actingAs($this->adminUath, 'sanctum')
        ->getJson("/api/v1/viaticos/{$this->viatico->id}/liquidacion")
        ->assertOk()
        ->assertJsonPath('datos', null);

    expect(LiquidacionViatico::count())->toBe(0);
});

// ── Acciones de Financiero ───────────────────────────────────────────

it('aprobar y rechazar es de quien aprueba viáticos', function (string $accion) {
    ($this->conItinerario)();
    $url = "/api/v1/viaticos/{$this->viatico->id}/{$accion}";
    $motivo = ['motivo' => 'La comisión no corresponde a la unidad'];

    foreach (['deTitular', 'ajeno', 'adminUath', 'asistenteUath'] as $quien) {
        $this->actingAs($this->{$quien}, 'sanctum')->postJson($url, $motivo)->assertForbidden();
    }

    $this->actingAs($this->financiero, 'sanctum')->postJson($url, $motivo)->assertOk();
})->with(['aprobar', 'rechazar']);

it('el anticipo, la comisión y la liquidación pendiente los marca quien opera', function () {
    $pasos = [
        'entregar-anticipo'            => EstadoViatico::APROBADO,
        'marcar-en-comision'           => EstadoViatico::CON_ANTICIPO,
        'marcar-pendiente-liquidacion' => EstadoViatico::EN_COMISION,
    ];

    foreach ($pasos as $accion => $estado) {
        ($this->en)($estado);
        $url = "/api/v1/viaticos/{$this->viatico->id}/{$accion}";

        $this->actingAs($this->deTitular, 'sanctum')->postJson($url)->assertForbidden();
        $this->actingAs($this->asistenteUath, 'sanctum')->postJson($url)->assertForbidden();
        $this->actingAs($this->financiero, 'sanctum')->postJson($url)->assertOk();
    }
});

it('devolver a corrección y contabilizar es de quien revisa la liquidación', function (string $accion) {
    ($this->en)(EstadoViatico::LIQUIDADO);
    // Contabilizar pide todos los comprobantes aceptados.
    comprobanteAceptado(LiquidacionViatico::create([
        'viatico_id' => $this->viatico->id, 'total_facturas' => 0,
        'fecha_liquidacion' => now()->toDateString(),
    ]));
    $url = "/api/v1/viaticos/{$this->viatico->id}/{$accion}";

    $motivo = ['motivo' => 'Falta la factura del hotel'];

    $this->actingAs($this->deTitular, 'sanctum')->postJson($url, $motivo)->assertForbidden();
    $this->actingAs($this->adminUath, 'sanctum')->postJson($url, $motivo)->assertForbidden();
    $this->actingAs($this->financiero, 'sanctum')->postJson($url, $motivo)->assertOk();
})->with(['devolver-correccion', 'contabilizar']);

it('las autorizaciones de vuelo las decide quien aprueba viáticos', function () {
    $catalogo = CatalogoTransporte::create(['nombre' => 'Aéreo', 'codigo' => 'AER', 'tipo_vehiculo' => 'aereo', 'requiere_autorizacion' => true]);
    $empresa = EmpresaTransporte::create(['catalogo_transporte_id' => $catalogo->id, 'nombre' => 'Avianca', 'codigo' => 'AV']);
    TramoViatico::create([
        'viatico_id' => $this->viatico->id,
        'origen_tipo' => 'nacional', 'origen_ciudad' => 'Esmeraldas',
        'destino_tipo' => 'nacional', 'destino_ciudad' => 'Quito',
        'empresa_transporte_id' => $empresa->id,
        'datetime_salida' => '2026-10-05 08:00:00', 'datetime_llegada' => '2026-10-05 09:00:00',
    ]);
    $autorizacion = AutorizacionVuelo::firstOrFail();

    foreach (['deTitular', 'ajeno', 'adminUath'] as $quien) {
        $this->actingAs($this->{$quien}, 'sanctum')->getJson('/api/v1/viaticos/vuelos')->assertForbidden();
        $this->actingAs($this->{$quien}, 'sanctum')->postJson("/api/v1/viaticos/vuelos/{$autorizacion->id}/aprobar")->assertForbidden();
    }
    $this->actingAs($this->ajeno, 'sanctum')
        ->postJson("/api/v1/viaticos/vuelos/{$autorizacion->id}/documento")
        ->assertForbidden();

    $this->actingAs($this->financiero, 'sanctum')->getJson('/api/v1/viaticos/vuelos')->assertOk()->assertJsonCount(1, 'datos');
    $this->actingAs($this->financiero, 'sanctum')->postJson("/api/v1/viaticos/vuelos/{$autorizacion->id}/rechazar")->assertOk();

    expect($autorizacion->fresh()->estado)->toBe('rechazada');
});

it('las rutas viejas de facturas por liquidación ya no existen', function () {
    $this->actingAs($this->financiero, 'sanctum')->getJson('/api/v1/liquidaciones/1/facturas')->assertNotFound();
});

// ── Migración ────────────────────────────────────────────────────────

it('la migración crea el rol financiero y deja a Talento Humano en consulta', function () {
    // La base de un despliegue anterior: sin rol financiero y con Talento
    // Humano operando los viáticos.
    $migracion = require database_path('migrations/2026_09_12_100000_crear_rol_financiero_para_viaticos.php');
    $migracion->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::where('name', 'financiero')->exists())->toBeFalse()
        ->and(Role::findByName('admin-uath', 'sanctum')->hasPermissionTo('gestionar-viaticos'))->toBeTrue()
        ->and(Role::findByName('asistente-uath', 'sanctum')->hasPermissionTo('gestionar-viaticos'))->toBeTrue();

    $migracion->up();
    $migracion->up(); // idempotente

    $financiero = Role::findByName('financiero', 'sanctum');
    expect($financiero->hasAllPermissions([
        'ver-viaticos-todos', 'aprobar-viatico', 'gestionar-viaticos', 'liquidar-viatico',
        'gestionar-tarifas-viatico', 'solicitar-viatico', 'acceso-autoservicio',
    ]))->toBeTrue();

    $admin = Role::findByName('admin-uath', 'sanctum');
    expect($admin->hasPermissionTo('ver-viaticos-todos'))->toBeTrue()
        ->and($admin->hasPermissionTo('gestionar-viaticos'))->toBeFalse()
        ->and($admin->hasPermissionTo('gestionar-tarifas-viatico'))->toBeFalse()
        ->and(Role::findByName('asistente-uath', 'sanctum')->hasPermissionTo('gestionar-viaticos'))->toBeFalse();

    // El rol que crea la migración tiene lo mismo que siembra el seeder.
    $permisos = fn () => Role::findByName('financiero', 'sanctum')->permissions()->pluck('name')->sort()->values();
    $deLaMigracion = $permisos();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    expect($deLaMigracion)->toEqual($permisos());
});
