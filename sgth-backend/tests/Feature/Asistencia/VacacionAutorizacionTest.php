<?php

/*
| Quién puede ver, registrar y resolver vacaciones, y gestionar los períodos.
|
| Hasta ahora las rutas de vacaciones y de períodos no comprobaban nada más
| allá de la sesión: un usuario sin ningún rol aprobaba vacaciones ajenas,
| descargaba el PDF de cualquiera, listaba las de toda la institución y
| disparaba «Generar todos». Ahora manda la matriz de `RolPermisoSeeder`, que
| ya tenía estas decisiones tomadas y nadie consultaba.
|
| Los usuarios de estos tests tienen roles concretos y ninguno es admin-ti: el
| `Gate::before` le da todo a admin-ti, así que probar con él no demostraría
| que la policy existe ni que se autodescubre.
*/

use App\Enums\RegimenLaboral;
use App\Models\Asistencia\PeriodoVacacion;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();
    Vacacion::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidadA = unidadDePrueba(['nombre' => 'Dirección A']);
    $this->unidadB = unidadDePrueba(['nombre' => 'Dirección B']);

    $cedula = 800000200;
    $this->servidor = function (UnidadAdministrativa $unidad, bool $jefe = false) use (&$cedula) {
        return Servidor::create([
            'cedula'                       => '0'.(++$cedula),
            'nombre'                       => 'Prueba',
            'apellido'                     => 'Vacaciones',
            'puesto_id'                    => ($jefe ? puestoJefeDePrueba($unidad) : puestoDePrueba($unidad))->id,
            'unidad_administrativa_id'     => $unidad->id,
            'regimen_laboral'              => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion'    => now()->subYears(3),
            'fecha_ingreso_sector_publico' => now()->subYears(3),
            'estado'                       => true,
        ]);
    };

    $this->usuario = function (?string $rol, ?Servidor $servidor = null) {
        $usuario = User::create([
            'email'        => uniqid('vac').'@example.com',
            'usuario_ti'   => uniqid('vac'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
            'servidor_id'  => $servidor?->id,
        ]);

        if ($rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    };

    $this->vacacion = fn (Servidor $servidor) => Vacacion::create([
        'servidor_id'              => $servidor->id,
        'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'fecha_inicio'             => now()->addDays(7)->toDateString(),
        'fecha_fin'                => now()->addDays(9)->toDateString(),
        'dias_solicitados'         => 3,
        'tipo_dias'                => 'habiles',
        'estado'                   => 'pendiente',
        'motivo'                   => 'vacaciones_anuales',
    ]);

    $this->servidorA = ($this->servidor)($this->unidadA);
    $this->servidorB = ($this->servidor)($this->unidadB);
    $this->vacacionA = ($this->vacacion)($this->servidorA);
    $this->vacacionB = ($this->vacacion)($this->servidorB);

    // Lejos de las vacaciones de arriba (a 7-9 días): una solicitud que se
    // cruzara con ellas se rechazaría por solapamiento, y el test pasaría o
    // fallaría según el día de la semana en que corriera.
    $lunes = now()->addWeeks(4)->next(Carbon::MONDAY);
    $this->solicitud = [
        'motivo'           => 'matrimonio',
        'fecha_inicio'     => $lunes->toDateString(),
        'fecha_fin'        => $lunes->copy()->addDay()->toDateString(),
        'dias_solicitados' => 2,
        'tipo_dias'        => 'habiles',
    ];
});

test('un usuario sin rol no entra a nada del módulo', function () {
    $this->actingAs(($this->usuario)(null), 'sanctum');
    $a = $this->servidorA->id;

    $this->getJson('/api/v1/asistencia/vacaciones')->assertForbidden();
    $this->putJson("/api/v1/asistencia/vacaciones/{$this->vacacionA->id}", ['estado' => 'aprobada'])->assertForbidden();
    $this->get("/api/v1/asistencia/vacaciones/{$this->vacacionA->id}/exportar")->assertForbidden();
    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$a}")->assertForbidden();
    $this->postJson('/api/v1/asistencia/vacaciones', $this->solicitud + ['servidor_id' => $a])->assertForbidden();

    $base = '/api/v1/asistencia/periodos-vacaciones';
    $this->getJson("{$base}/servidores/{$a}/resumen")->assertForbidden();
    $this->postJson("{$base}/servidores/{$a}/generar", ['anio' => now()->year])->assertForbidden();
    $this->getJson("{$base}/servidores/{$a}/recalcular-cerrado/previsualizacion?anio=".now()->year)->assertForbidden();
    $this->postJson("{$base}/servidores/{$a}/recalcular-cerrado", ['anio' => now()->year])->assertForbidden();
    $this->postJson("{$base}/generar-todos", ['anio' => now()->year])->assertForbidden();

    // Que el 403 no llegue después de haber hecho el daño.
    expect($this->vacacionA->fresh()->estado)->toBe('pendiente')
        ->and(PeriodoVacacion::count())->toBe(0)
        ->and(Vacacion::count())->toBe(2);
});

test('el jefe de unidad ve y exporta solo lo de su unidad, y no resuelve', function () {
    $jefe = ($this->usuario)('jefe-unidad', ($this->servidor)($this->unidadA, jefe: true));
    $this->actingAs($jefe, 'sanctum');

    $ids = collect($this->getJson('/api/v1/asistencia/vacaciones')->assertOk()->json('datos.data'))->pluck('id');
    expect($ids)->toContain($this->vacacionA->id)
        ->not->toContain($this->vacacionB->id);

    $this->get("/api/v1/asistencia/vacaciones/{$this->vacacionA->id}/exportar")->assertOk();
    $this->get("/api/v1/asistencia/vacaciones/{$this->vacacionB->id}/exportar")->assertForbidden();

    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$this->servidorA->id}")->assertOk();
    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$this->servidorB->id}")->assertForbidden();

    $this->putJson("/api/v1/asistencia/vacaciones/{$this->vacacionA->id}", ['estado' => 'aprobada'])
        ->assertForbidden();
});

test('asistente de Talento Humano ve toda la institución pero no resuelve ni gestiona períodos', function () {
    // Así está en la matriz: asistente-uath tiene ver-asistencia-todos, pero
    // gestionar-vacaciones y aprobar-vacaciones son solo de admin-uath.
    $this->actingAs(($this->usuario)('asistente-uath'), 'sanctum');

    $ids = collect($this->getJson('/api/v1/asistencia/vacaciones')->assertOk()->json('datos.data'))->pluck('id');
    expect($ids)->toContain($this->vacacionA->id)->toContain($this->vacacionB->id);

    $this->putJson("/api/v1/asistencia/vacaciones/{$this->vacacionA->id}", ['estado' => 'aprobada'])->assertForbidden();
    $this->postJson('/api/v1/asistencia/periodos-vacaciones/generar-todos', ['anio' => now()->year])->assertForbidden();
});

test('Talento Humano aprueba y genera períodos, pero no resuelve su propia solicitud', function () {
    $servidorUath = ($this->servidor)($this->unidadA);
    $this->actingAs(($this->usuario)('admin-uath', $servidorUath), 'sanctum');

    // Primero los períodos: aprobar unas vacaciones anuales descuenta días, y
    // sin período abierto del año no hay de dónde descontarlos.
    $anio = $this->vacacionA->fecha_inicio->year;
    $this->postJson('/api/v1/asistencia/periodos-vacaciones/generar-todos', ['anio' => $anio])->assertOk();
    $this->putJson("/api/v1/asistencia/vacaciones/{$this->vacacionA->id}", ['estado' => 'aprobada'])->assertOk();

    $propia = ($this->vacacion)($servidorUath);
    $respuesta = $this->putJson("/api/v1/asistencia/vacaciones/{$propia->id}", ['estado' => 'aprobada']);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('propia solicitud')
        ->and($propia->fresh()->estado)->toBe('pendiente');
});

test('un servidor registra la suya y consulta su saldo, pero no la de otros', function () {
    $this->actingAs(($this->usuario)('servidor', $this->servidorA), 'sanctum');

    $this->postJson('/api/v1/asistencia/vacaciones', $this->solicitud)->assertCreated();
    $this->postJson('/api/v1/asistencia/vacaciones', $this->solicitud + ['servidor_id' => $this->servidorB->id])
        ->assertForbidden();

    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$this->servidorA->id}")->assertOk();
    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$this->servidorB->id}")->assertForbidden();

    // El listado de la institución no es para él: lo suyo lo ve en el
    // autoservicio.
    $this->getJson('/api/v1/asistencia/vacaciones')->assertForbidden();
    $this->get("/api/v1/asistencia/vacaciones/{$this->vacacionA->id}/exportar")->assertOk();
});
