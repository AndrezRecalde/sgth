<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoSubrogacion;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\TipoSubrogacion;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\FirmanteAccionPersonalService;
use App\Services\Expediente\SubrogacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'PREF-01', 'nombre' => 'Prefectura Provincial', 'nivel' => 1,
        'es_maxima_autoridad' => true, 'estado' => true,
    ]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510106', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-PREF', 'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 1, 'es_jefe' => true, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(
            ['nombre' => 'Prefecto/a Provincial']
        )->id,
        'partida_presupuestaria_id' => $this->partida->id,
    ]);

    $this->contador = 0;

    $this->servidorCon = function (?int $puestoId = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'   => str_pad((string) (8000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'   => 'Servidor',
            'apellido' => 'Caduca'.$this->contador,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        if ($puestoId) {
            ContratoServidor::create([
                'servidor_id'              => $servidor->id,
                'tipo_nombramiento'        => 'nombramiento_permanente',
                'unidad_administrativa_id' => $this->unidad->id,
                'puesto_id'                => $puestoId,
                'fecha_inicio'             => '2018-01-01',
                'estado'                   => 'vigente',
            ]);
        }

        return $servidor->fresh();
    };

    $this->service   = app(SubrogacionService::class);
    $this->firmantes = app(FirmanteAccionPersonalService::class);

    /** Crea una subrogación ya ACTIVA en el rango indicado, sin pasar por el flujo. */
    $this->subrogacionActiva = function (string $inicio, string $fin, ?Servidor $titular = null): Subrogacion {
        $subrogante = ($this->servidorCon)();

        return Subrogacion::create([
            'tipo'                     => TipoSubrogacion::SUBROGACION->value,
            'servidor_subrogante_id'   => $subrogante->id,
            'servidor_subrogado_id'    => $titular?->id ?? ($this->servidorCon)()->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_subrogado_id'      => $this->puesto->id,
            'fecha_inicio'             => $inicio,
            'fecha_fin'                => $fin,
            'motivo'                   => 'vacaciones',
            'estado'                   => EstadoSubrogacion::ACTIVA->value,
            'registrado_por'           => $this->user->id,
        ]);
    };
});

// ── Caducidad ───────────────────────────────────────────────────

test('cierra las que ya cumplieron su plazo', function () {
    $vencida = ($this->subrogacionActiva)('2026-01-01', '2026-03-31');
    $vigente = ($this->subrogacionActiva)(now()->subDay()->toDateString(), now()->addMonth()->toDateString());

    $resultado = $this->service->caducarVencidas();

    expect($resultado['caducadas'])->toBe(1)
        ->and($vencida->fresh()->estado)->toBe(EstadoSubrogacion::FINALIZADA)
        ->and($vigente->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA);
});

test('la que termina hoy todavía no caduca', function () {
    $hoy = ($this->subrogacionActiva)(now()->subMonth()->toDateString(), now()->toDateString());

    $this->service->caducarVencidas();

    expect($hoy->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA);
});

test('no toca pendientes ni canceladas', function () {
    $pendiente = ($this->subrogacionActiva)('2026-01-01', '2026-03-31');
    $pendiente->update(['estado' => EstadoSubrogacion::PENDIENTE->value]);

    $cancelada = ($this->subrogacionActiva)('2026-01-01', '2026-03-31');
    $cancelada->update(['estado' => EstadoSubrogacion::CANCELADA->value]);

    $this->service->caducarVencidas();

    expect($pendiente->fresh()->estado)->toBe(EstadoSubrogacion::PENDIENTE)
        ->and($cancelada->fresh()->estado)->toBe(EstadoSubrogacion::CANCELADA);
});

test('el comando reporta cuántas cerró', function () {
    ($this->subrogacionActiva)('2026-01-01', '2026-03-31');

    $this->artisan('sgth:subrogaciones:caducar')
        ->expectsOutputToContain('1 subrogación(es)/encargo(s) finalizados')
        ->assertSuccessful();
});

test('la fecha de corte se puede fijar para reconstruir un estado pasado', function () {
    $s = ($this->subrogacionActiva)('2026-01-01', '2026-03-31');

    $this->artisan('sgth:subrogaciones:caducar', ['--fecha' => '2026-02-15'])
        ->assertSuccessful();

    expect($s->fresh()->estado)->toBe(EstadoSubrogacion::ACTIVA);
});

/**
 * La caducidad pone al día el registro; nunca fue lo que contenía el poder.
 * Quien firma se resuelve acotando por fecha, así que una vencida sin caducar
 * tampoco firmaba. Este test fija esa garantía para que no se pierda si algún
 * día alguien "optimiza" el filtro de fechas confiando en el estado.
 */
test('una vencida sin caducar tampoco otorga la firma', function () {
    $titular = ($this->servidorCon)($this->puesto->id);
    ($this->subrogacionActiva)('2026-01-01', '2026-03-31', $titular);

    $firma = $this->firmantes->resolver(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, now()->toDateString());

    expect($firma['subrogado'])->toBeFalse()
        ->and($firma['servidor']?->id)->toBe($titular->id);
});

// ── El listado no depende del scheduler ─────────────────────────

test('el listado de la pantalla excluye las vencidas aunque nadie las haya caducado', function () {
    ($this->subrogacionActiva)('2026-01-01', '2026-03-31');
    ($this->subrogacionActiva)(now()->subDay()->toDateString(), now()->addMonth()->toDateString());

    expect($this->service->listarVigentes())->toHaveCount(1);
});

// ── Visibilidad ─────────────────────────────────────────────────

test('el titular subrogado también la ve en su expediente', function () {
    $titular     = ($this->servidorCon)($this->puesto->id);
    $subrogacion = ($this->subrogacionActiva)(
        now()->subDay()->toDateString(), now()->addMonth()->toDateString(), $titular
    );

    $historial = $this->service->listarPorServidor($titular->id);

    expect($historial)->toHaveCount(1)
        ->and($historial->first()->id)->toBe($subrogacion->id);

    // Y el subrogante sigue viéndola desde su lado.
    expect($this->service->listarPorServidor($subrogacion->servidor_subrogante_id))
        ->toHaveCount(1);
});

test('el organigrama muestra qué unidades tienen alguien subrogando', function () {
    $subrogacion = ($this->subrogacionActiva)(
        now()->subDay()->toDateString(), now()->addMonth()->toDateString()
    );

    $this->user->givePermissionTo(
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'ver-estructura', 'guard_name' => 'sanctum',
        ])
    );

    $response = $this->getJson('/api/v1/estructura/organigrama')->assertOk();

    $unidad = collect($response->json('datos'))
        ->firstWhere('id', $this->unidad->id);

    expect($unidad['subrogaciones_vigentes'])->toHaveCount(1)
        ->and($unidad['subrogaciones_vigentes'][0]['puesto'])->toBe('Prefecto/a Provincial')
        ->and($unidad['subrogaciones_vigentes'][0]['id'])->toBe($subrogacion->id);
});

test('una unidad sin subrogación vigente la lista vacía', function () {
    ($this->subrogacionActiva)('2026-01-01', '2026-03-31'); // vencida

    $this->user->givePermissionTo(
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'ver-estructura', 'guard_name' => 'sanctum',
        ])
    );

    $response = $this->getJson('/api/v1/estructura/organigrama')->assertOk();

    $unidad = collect($response->json('datos'))
        ->firstWhere('id', $this->unidad->id);

    expect($unidad['subrogaciones_vigentes'])->toBe([]);
});
