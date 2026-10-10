<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 1.3 del diseño de Acciones de Personal (6.3; TH 22 y 24):
|  - cada paso del trámite con su permiso: el asistente prepara y notifica, el
|    director suscribe, registra y anula;
|  - nadie tramita una acción sobre sí mismo, y eso no tiene atajo;
|  - el titular ve sus actos, no lo que se le está preparando.
*/
beforeEach(function () {
    foreach (['admin-uath', 'asistente-uath', 'admin-ti'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    permisosDeAccionesPersonal();

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-PER', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-PER', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->contador = 0;

    $this->servidor = function (): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'                    => str_pad((string) (8200000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Permisos'.$this->contador,
            'regimen_laboral'           => 'losep',
            'puesto_id'                 => $this->puesto->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2018-01-01',
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    /** Un usuario con ese rol; con `$servidor`, es esa persona. */
    $this->usuario = function (?string $rol, ?Servidor $servidor = null): User {
        $usuario = User::factory()->create(['servidor_id' => $servidor?->id]);

        if ($rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    };

    $this->accion = fn (Servidor $servidor, EstadoAccionPersonal $estado, array $datos = []) => MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION->value,
        'estado'          => $estado,
        'descripcion'     => "Licencia en {$estado->value}",
        'fecha_efectiva'  => '2026-10-15',
        'fecha_inicio'    => '2026-10-15',
        'fecha_fin'       => '2026-11-15',
        ...$datos,
    ]);

    $this->crear = fn (Servidor $servidor) => $this->postJson(
        "/api/v1/expediente/servidores/{$servidor->id}/movimientos",
        [
            'clase'                    => 'licencia_sin_remuneracion',
            'causal'                   => 'servicio_militar',
            'descripcion'              => 'Licencia de prueba',
            'fecha_efectiva'           => '2026-10-15',
            'fecha_inicio'             => '2026-10-15',
            'fecha_fin'                => '2026-11-15',
            'requiere_dictamen_medico' => false,
        ]
    );

    $this->transicionar = fn (MovimientoPersonal $m, string $estado) => $this->putJson(
        "/api/v1/expediente/movimientos/{$m->id}/transicionar",
        ['estado' => $estado, 'motivo_anulacion' => $estado === 'anulada' ? 'Se registró por error.' : null],
    );
});

// ── Cada paso con su permiso ────────────────────────────────────

test('el asistente prepara: crea y corrige borradores', function () {
    $servidor = ($this->servidor)();
    $this->actingAs(($this->usuario)('asistente-uath'), 'sanctum');

    $creada = ($this->crear)($servidor)->assertCreated();

    $this->putJson("/api/v1/expediente/movimientos/{$creada->json('datos.id')}", [
        'descripcion' => 'Licencia corregida por el asistente',
    ])->assertOk();
});

test('el asistente notifica, pero no suscribe, ni registra, ni anula', function () {
    $servidor = ($this->servidor)();
    $this->actingAs(($this->usuario)('asistente-uath'), 'sanctum');

    ($this->transicionar)(($this->accion)($servidor, EstadoAccionPersonal::BORRADOR), 'suscrita')->assertForbidden();
    ($this->transicionar)(($this->accion)($servidor, EstadoAccionPersonal::SUSCRITA), 'registrada')->assertForbidden();
    ($this->transicionar)(($this->accion)($servidor, EstadoAccionPersonal::BORRADOR), 'anulada')->assertForbidden();

    $registrada = ($this->accion)($servidor, EstadoAccionPersonal::REGISTRADA, ['codigo_registro' => 'AP-2026-0901']);

    ($this->transicionar)($registrada, 'notificada')->assertOk();
});

test('el director hace todo el trámite', function () {
    $servidor = ($this->servidor)();
    $this->actingAs(($this->usuario)('admin-uath'), 'sanctum');

    $borrador = ($this->accion)($servidor, EstadoAccionPersonal::BORRADOR);

    ($this->transicionar)($borrador, 'suscrita')->assertOk();
    ($this->transicionar)($borrador->fresh(), 'registrada')->assertOk();
    ($this->transicionar)($borrador->fresh(), 'notificada')->assertOk();
    ($this->transicionar)($borrador->fresh(), 'anulada')->assertOk();
});

test('sin ninguno de los permisos no se entra a la bandeja ni al catálogo ni se crea nada', function () {
    $servidor = ($this->servidor)();
    $this->actingAs(($this->usuario)(null), 'sanctum');

    $this->getJson('/api/v1/expediente/movimientos')->assertForbidden();
    $this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertForbidden();
    ($this->crear)($servidor)->assertForbidden();
});

test('la migración y el seeder reparten los mismos permisos', function () {
    $this->seed(RolPermisoSeeder::class);

    $delTramite = fn (string $rol) => Role::findByName($rol, 'sanctum')->permissions
        ->pluck('name')
        ->filter(fn (string $p) => str_ends_with($p, '-accion-personal'))
        ->sort()->values()->all();

    expect($delTramite('admin-uath'))->toBe([
        'anular-accion-personal', 'notificar-accion-personal', 'preparar-accion-personal',
        'registrar-accion-personal', 'suscribir-accion-personal',
    ])->and($delTramite('asistente-uath'))->toBe([
        'notificar-accion-personal', 'preparar-accion-personal',
    ]);
});

// ── Nadie tramita sobre sí mismo ────────────────────────────────

test('el director no se prepara, ni se corrige, ni se tramita su propia acción', function () {
    $director = ($this->servidor)();
    $this->actingAs(($this->usuario)('admin-uath', $director), 'sanctum');

    ($this->crear)($director)->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'sobre usted mismo'));

    $suya = ($this->accion)($director, EstadoAccionPersonal::BORRADOR);

    $this->putJson("/api/v1/expediente/movimientos/{$suya->id}", ['descripcion' => 'x'])->assertStatus(422);
    ($this->transicionar)($suya, 'suscrita')->assertStatus(422);
    ($this->transicionar)($suya, 'anulada')->assertStatus(422);

    expect($suya->fresh()->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('pero sí tramita las de los demás', function () {
    $director = ($this->servidor)();
    $this->actingAs(($this->usuario)('admin-uath', $director), 'sanctum');

    ($this->crear)(($this->servidor)())->assertCreated();
});

test('el atajo de admin-ti no se salta la regla', function () {
    $tecnico = ($this->servidor)();
    $this->actingAs(($this->usuario)('admin-ti', $tecnico), 'sanctum');

    $suya = ($this->accion)($tecnico, EstadoAccionPersonal::BORRADOR);

    expect(fn () => app(MovimientoPersonalStateService::class)
        ->transicionar($suya, EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class, 'sobre usted mismo');
});

test('una tarea sin usuario no tiene a quién aplicarle la regla', function () {
    $servidor = ($this->servidor)();
    $suscrita = ($this->accion)($servidor, EstadoAccionPersonal::BORRADOR);

    app(MovimientoPersonalStateService::class)->transicionar($suscrita, EstadoAccionPersonal::SUSCRITA);

    expect($suscrita->fresh()->estado)->toBe(EstadoAccionPersonal::SUSCRITA);
});

// ── Lo que ve el titular ────────────────────────────────────────

test('el titular ve sus actos, no lo que se le está preparando', function () {
    $titular = ($this->servidor)();

    ($this->accion)($titular, EstadoAccionPersonal::BORRADOR);
    ($this->accion)($titular, EstadoAccionPersonal::SUSCRITA);
    // Anulada en borrador: nunca fue nada.
    ($this->accion)($titular, EstadoAccionPersonal::ANULADA);
    $registrada = ($this->accion)($titular, EstadoAccionPersonal::REGISTRADA, ['codigo_registro' => 'AP-2026-0910']);
    $notificada = ($this->accion)($titular, EstadoAccionPersonal::NOTIFICADA, ['codigo_registro' => 'AP-2026-0911']);
    // Anulada después de tener número: circuló, y su anulación le concierne.
    $anulada = ($this->accion)($titular, EstadoAccionPersonal::ANULADA, ['codigo_registro' => 'AP-2026-0912']);

    $this->actingAs(($this->usuario)(null, $titular), 'sanctum');

    $ids = collect($this->getJson("/api/v1/expediente/servidores/{$titular->id}/movimientos")
        ->assertOk()->json('datos'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([$registrada->id, $notificada->id, $anulada->id]);
});

test('el borrador no existe para el titular, ni pidiéndolo por su número', function () {
    $titular = ($this->servidor)();
    $borrador = ($this->accion)($titular, EstadoAccionPersonal::BORRADOR);
    $registrada = ($this->accion)($titular, EstadoAccionPersonal::REGISTRADA, ['codigo_registro' => 'AP-2026-0920']);

    $this->actingAs(($this->usuario)(null, $titular), 'sanctum');

    $this->getJson("/api/v1/expediente/movimientos/{$borrador->id}")->assertNotFound();
    $this->getJson("/api/v1/expediente/movimientos/{$registrada->id}")->assertOk();
});

test('quien trabaja en Talento Humano tampoco ve lo suyo en preparación, ni en su expediente ni en la bandeja', function () {
    $asistente = ($this->servidor)();
    $otro = ($this->servidor)();

    $suyaEnBorrador = ($this->accion)($asistente, EstadoAccionPersonal::BORRADOR);
    $suyaRegistrada = ($this->accion)($asistente, EstadoAccionPersonal::REGISTRADA, ['codigo_registro' => 'AP-2026-0930']);
    $ajenaEnBorrador = ($this->accion)($otro, EstadoAccionPersonal::BORRADOR);

    $this->actingAs(($this->usuario)('asistente-uath', $asistente), 'sanctum');

    $enSuExpediente = collect($this->getJson("/api/v1/expediente/servidores/{$asistente->id}/movimientos")
        ->assertOk()->json('datos'))->pluck('id')->all();

    $enLaBandeja = collect($this->getJson('/api/v1/expediente/movimientos')
        ->assertOk()->json('datos.data'))->pluck('id')->all();

    expect($enSuExpediente)->toBe([$suyaRegistrada->id])
        ->and($enLaBandeja)->toContain($suyaRegistrada->id, $ajenaEnBorrador->id)
        ->and($enLaBandeja)->not->toContain($suyaEnBorrador->id);
});

// ── Lo que la pantalla sabe hacer con cada acción ───────────────

test('cada acción dice qué pasos puede dar quien la mira', function () {
    $servidor = ($this->servidor)();
    $borrador = ($this->accion)($servidor, EstadoAccionPersonal::BORRADOR);
    $registrada = ($this->accion)($servidor, EstadoAccionPersonal::REGISTRADA, ['codigo_registro' => 'AP-2026-0940']);

    $pasos = fn (MovimientoPersonal $m) => $this->getJson("/api/v1/expediente/movimientos/{$m->id}")
        ->assertOk()->json('datos');

    $this->actingAs(($this->usuario)('admin-uath'), 'sanctum');
    expect($pasos($borrador)['transiciones_permitidas'])->toBe(['suscrita', 'anulada'])
        ->and($pasos($borrador)['puede_editar'])->toBeTrue()
        ->and($pasos($registrada)['transiciones_permitidas'])->toBe(['notificada', 'anulada'])
        ->and($pasos($registrada)['puede_editar'])->toBeFalse();

    $this->actingAs(($this->usuario)('asistente-uath'), 'sanctum');
    expect($pasos($borrador)['transiciones_permitidas'])->toBe([])
        ->and($pasos($borrador)['puede_editar'])->toBeTrue()
        ->and($pasos($registrada)['transiciones_permitidas'])->toBe(['notificada']);
});

test('sobre la propia acción no hay ningún paso, aunque se tengan todos los permisos', function () {
    $director = ($this->servidor)();
    $registrada = ($this->accion)($director, EstadoAccionPersonal::REGISTRADA, ['codigo_registro' => 'AP-2026-0950']);

    $this->actingAs(($this->usuario)('admin-uath', $director), 'sanctum');

    $datos = $this->getJson("/api/v1/expediente/movimientos/{$registrada->id}")->assertOk()->json('datos');

    expect($datos['transiciones_permitidas'])->toBe([])
        ->and($datos['puede_editar'])->toBeFalse();
});
