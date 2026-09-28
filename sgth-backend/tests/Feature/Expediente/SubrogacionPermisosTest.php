<?php

namespace Tests\Feature\Expediente;

use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Quién entra a las subrogaciones y qué rechaza el borde HTTP.
 *
 * Hasta el 2026-09-28 la respuesta a lo primero era «cualquiera»: el policy
 * autorizaba la lectura a todo usuario autenticado y las rutas no pedían rol.
 */
beforeEach(function () {
    foreach ([
        'admin-uath', 'asistente-uath', 'auditor', 'maxima-autoridad',
        'admin-ti', 'servidor', 'jefe-unidad',
    ] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'PREF-01', 'nombre' => 'Prefectura Provincial', 'nivel' => 1,
        'es_maxima_autoridad' => true, 'estado' => true,
    ]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510512', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-PREF', 'unidad_administrativa_id' => $this->unidad->id,
        'plazas' => 1, 'es_jefe' => true, 'activo' => true,
        'cargo_id' => Cargo::firstOrCreate(['nombre' => 'Prefecto/a Provincial'])->id,
        'partida_presupuestaria_id' => $this->partida->id,
    ]);

    $this->contador = 0;

    $this->servidorCon = function (?int $puestoId = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'   => str_pad((string) (5000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'   => 'Servidor',
            'apellido' => 'Permisos'.$this->contador,
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

    $this->comoRol = function (string $rol, ?int $servidorId = null): User {
        $user = User::factory()->create(['servidor_id' => $servidorId]);
        $user->assignRole($rol);
        $this->actingAs($user, 'sanctum');

        return $user;
    };

    $this->datosValidos = function (): array {
        $titular    = ($this->servidorCon)($this->puesto->id);
        $subrogante = ($this->servidorCon)();

        return [
            'tipo'                     => 'subrogacion',
            'servidor_subrogante_id'   => $subrogante->id,
            'servidor_subrogado_id'    => $titular->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_subrogado_id'      => $this->puesto->id,
            'fecha_inicio'             => now()->addDay()->toDateString(),
            'fecha_fin'                => now()->addMonth()->toDateString(),
            'motivo'                   => 'vacaciones',
        ];
    };
});

// ── Lectura ─────────────────────────────────────────────────────

test('un servidor raso no lista las subrogaciones de la institución', function () {
    ($this->comoRol)('servidor');

    $this->getJson('/api/v1/expediente/subrogaciones/vigentes')->assertForbidden();
});

test('un jefe de unidad tampoco: ver el módulo no viene con el subsistema', function () {
    ($this->comoRol)('jefe-unidad');

    $this->getJson('/api/v1/expediente/subrogaciones/vigentes')->assertForbidden();
});

test('UATH, auditoría y la máxima autoridad sí leen el listado', function (string $rol) {
    ($this->comoRol)($rol);

    $this->getJson('/api/v1/expediente/subrogaciones/vigentes')->assertOk();
})->with(['admin-uath', 'asistente-uath', 'auditor', 'maxima-autoridad', 'admin-ti']);

test('el historial de otro servidor no se abre sin ser UATH ni auditoría', function () {
    $ajeno = ($this->servidorCon)();
    $propio = ($this->servidorCon)();
    ($this->comoRol)('servidor', $propio->id);

    $this->getJson("/api/v1/expediente/subrogaciones/servidor/{$ajeno->id}")
        ->assertForbidden();
});

test('cada servidor sí abre su propio historial', function () {
    $propio = ($this->servidorCon)();
    ($this->comoRol)('servidor', $propio->id);

    $this->getJson("/api/v1/expediente/subrogaciones/servidor/{$propio->id}")
        ->assertOk();
});

// ── Escritura ───────────────────────────────────────────────────

test('el asistente de UATH registra: ya registra acciones de personal', function () {
    $datos = ($this->datosValidos)();
    ($this->comoRol)('asistente-uath');

    $this->postJson('/api/v1/expediente/subrogaciones', $datos)->assertCreated();
});

test('auditoría lee pero no escribe', function () {
    $datos = ($this->datosValidos)();
    ($this->comoRol)('auditor');

    $this->postJson('/api/v1/expediente/subrogaciones', $datos)->assertForbidden();
});

test('un servidor raso no registra', function () {
    $datos = ($this->datosValidos)();
    ($this->comoRol)('servidor');

    $this->postJson('/api/v1/expediente/subrogaciones', $datos)->assertForbidden();
});

// ── Validación del borde HTTP ───────────────────────────────────

/**
 * `motivo` se validaba como `required|string` mientras la columna se castea a
 * `MotivoSubrogacion`: cualquier valor fuera de lista reventaba en el cast de
 * Eloquent y el API respondía 500. El 422 es la diferencia entre «corrige este
 * campo» y «algo se rompió».
 */
test('un motivo fuera del enum se rechaza con 422, no con un 500', function () {
    ($this->comoRol)('admin-uath');

    $this->postJson('/api/v1/expediente/subrogaciones', [
        ...($this->datosValidos)(),
        'motivo' => 'lo_que_sea',
    ])->assertStatus(422)->assertJsonValidationErrors('motivo', 'errores');
});

test('un tipo fuera del enum también', function () {
    ($this->comoRol)('admin-uath');

    $this->postJson('/api/v1/expediente/subrogaciones', [
        ...($this->datosValidos)(),
        'tipo' => 'comision',
    ])->assertStatus(422)->assertJsonValidationErrors('tipo', 'errores');
});

test('el motivo de una cancelación no pasa de 500 caracteres', function () {
    ($this->comoRol)('admin-uath');

    $this->putJson('/api/v1/expediente/subrogaciones/1/cancelar', [
        'motivo' => str_repeat('a', 501),
    ])->assertStatus(422)->assertJsonValidationErrors('motivo', 'errores');
});

test('el mensaje de validación nombra el campo en español', function () {
    ($this->comoRol)('admin-uath');

    $respuesta = $this->postJson('/api/v1/expediente/subrogaciones', [
        ...($this->datosValidos)(),
        'servidor_subrogante_id' => null,
    ])->assertStatus(422);

    expect($respuesta->json('errores.servidor_subrogante_id.0'))
        ->toContain('servidor subrogante');
});
