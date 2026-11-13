<?php

/*
| El jefe ve a su equipo por su puesto, no por un rol (decisión del 2026-11-13).
|
| Dirige una unidad quien tiene contrato vigente en su puesto de jefatura, o
| quien lo subroga o lo tiene encargado ese día: la misma regla con la que
| `FirmanteOrganigramaService` decide quién firma. Hasta ahora lo decidía el
| rol `jefe-unidad`, que TI daba a mano y nada retiraba cuando la persona
| dejaba la jefatura.
|
| Aquí ningún jefe tiene ese rol: todos son `servidor`.
*/

use App\Enums\EstadoPermiso;
use App\Enums\RegimenLaboral;
use App\Enums\TipoPermiso;
use App\Models\Asistencia\PermisoServidor;
use App\Models\Asistencia\Vacacion;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Estructura\JefaturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidadA = unidadDePrueba(['nombre' => 'Dirección A']);
    $this->unidadB = unidadDePrueba(['nombre' => 'Dirección B']);

    $this->jefaturaA = puestoJefeDePrueba($this->unidadA, 'Director A');
    $this->jefaturaB = puestoJefeDePrueba($this->unidadB, 'Director B');

    $this->jefeA        = jpServidor($this->jefaturaA, '0811111111', 'Jefa');
    $this->subordinadoA = jpServidor(puestoDePrueba($this->unidadA), '0812222222', 'Subordinado');
    $this->jefeB        = jpServidor($this->jefaturaB, '0813333333', 'Jefe');
    $this->servidorB    = jpServidor(puestoDePrueba($this->unidadB, 'Asistente'), '0814444444', 'Ajeno');

    $this->usuarioJefeA = jpUsuario($this->jefeA);

    $this->permisoA = jpPermiso($this->subordinadoA, 'PER-JP-A');
    $this->permisoB = jpPermiso($this->servidorB, 'PER-JP-B');
});

function jpServidor(Puesto $puesto, string $cedula, string $apellido, string $estado = 'vigente'): Servidor
{
    $servidor = Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Servidor', 'apellido' => $apellido,
        'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'puesto_id' => $puesto->id,
        'fecha_inicio' => '2020-01-02', 'estado' => $estado,
    ]);

    return $servidor->fresh();
}

function jpUsuario(Servidor $servidor): User
{
    $usuario = User::factory()->create(['servidor_id' => $servidor->id, 'primer_login' => false]);
    $usuario->assignRole('servidor');

    return $usuario->fresh();
}

function jpPermiso(Servidor $servidor, string $folio, ?Servidor $jefe = null): PermisoServidor
{
    return PermisoServidor::create([
        'servidor_id' => $servidor->id, 'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'jefe_id' => $jefe?->id, 'tipo' => TipoPermiso::PERSONAL->value,
        'fecha' => now()->addDay()->format('Y-m-d'), 'hora_inicio' => '08:00', 'hora_fin' => '10:00',
        'observacion' => 'Motivo reservado', 'estado' => EstadoPermiso::PENDIENTE->value,
        'vence_en' => now()->addDays(4), 'folio' => $folio,
    ]);
}

function jpVacacion(Servidor $servidor): Vacacion
{
    return Vacacion::create([
        'servidor_id' => $servidor->id, 'unidad_administrativa_id' => $servidor->unidad_administrativa_id,
        'fecha_inicio' => now()->addDays(7)->toDateString(), 'fecha_fin' => now()->addDays(9)->toDateString(),
        'dias_solicitados' => 3, 'tipo_dias' => 'habiles', 'estado' => 'pendiente', 'motivo' => 'vacaciones_anuales',
    ]);
}

function jpSubrogacion(Servidor $subrogante, Puesto $puesto, Servidor $titular, string $tipo = 'subrogacion', array $cambios = []): Subrogacion
{
    return Subrogacion::create(array_merge([
        'tipo' => $tipo, 'servidor_subrogante_id' => $subrogante->id, 'servidor_subrogado_id' => $titular->id,
        'unidad_administrativa_id' => $puesto->unidad_administrativa_id, 'puesto_subrogado_id' => $puesto->id,
        'fecha_inicio' => now()->subDays(2)->toDateString(), 'fecha_fin' => now()->addDays(10)->toDateString(),
        'motivo' => 'vacaciones', 'estado' => 'activa', 'registrado_por' => User::factory()->create()->id,
    ], $cambios));
}

/** Los folios que le devuelve el listado de permisos. */
function jpFolios(User $usuario): array
{
    return collect(test()->actingAs($usuario, 'sanctum')
        ->getJson('/api/v1/asistencia/permisos?per_page=50')
        ->assertOk()
        ->json('datos.data'))->pluck('folio')->sort()->values()->all();
}

// ── Quién dirige ─────────────────────────────────────────────────────

it('el titular de la jefatura dirige su unidad, y su subordinado no', function () {
    $jefaturas = app(JefaturaService::class);

    expect($jefaturas->jefaturasDe($this->jefeA->id)->all())->toBe([
        ['unidad_id' => $this->unidadA->id, 'unidad' => 'Dirección A', 'como' => 'titular'],
    ])
        ->and($jefaturas->unidadesQueDirige($this->subordinadoA->id))->toBe([])
        ->and($jefaturas->unidadesQueDirige(null))->toBe([]);
});

it('el contrato que ya no está vigente no da la unidad', function () {
    ContratoServidor::where('servidor_id', $this->jefeA->id)->update(['estado' => 'terminado']);

    expect(app(JefaturaService::class)->unidadesQueDirige($this->jefeA->id))->toBe([]);
});

it('el subrogante y el encargado la dirigen mientras dure, y el titular también', function () {
    jpSubrogacion($this->servidorB, $this->jefaturaB, $this->jefeB);
    $encargado = jpServidor(puestoDePrueba($this->unidadA, 'Técnico'), '0815555555', 'Encargado');
    jpSubrogacion($encargado, $this->jefaturaB, $this->jefeB, 'encargo');

    $jefaturas = app(JefaturaService::class);

    expect($jefaturas->jefaturasDe($this->servidorB->id)->pluck('como', 'unidad_id')->all())
        ->toBe([$this->unidadB->id => 'subrogacion'])
        ->and($jefaturas->jefaturasDe($encargado->id)->pluck('como', 'unidad_id')->all())
        ->toBe([$this->unidadB->id => 'encargo'])
        ->and($jefaturas->unidadesQueDirige($this->jefeB->id))->toBe([$this->unidadB->id]);
});

it('la subrogación vencida, la cancelada y la de un puesto sin jefatura no dan equipo', function () {
    jpSubrogacion($this->servidorB, $this->jefaturaB, $this->jefeB, cambios: [
        'fecha_inicio' => now()->subDays(20)->toDateString(), 'fecha_fin' => now()->subDay()->toDateString(),
    ]);
    jpSubrogacion($this->servidorB, $this->jefaturaB, $this->jefeB, cambios: ['estado' => 'cancelada']);
    jpSubrogacion($this->servidorB, Puesto::find($this->subordinadoA->puesto_id), $this->subordinadoA);

    expect(app(JefaturaService::class)->unidadesQueDirige($this->servidorB->id))->toBe([]);
});

// ── Permisos ─────────────────────────────────────────────────────────

it('el jefe por su puesto ve los permisos de su unidad, sin el motivo, y no los de otra', function () {
    expect(jpFolios($this->usuarioJefeA))->toBe(['PER-JP-A']);

    $this->actingAs($this->usuarioJefeA, 'sanctum')
        ->getJson("/api/v1/asistencia/permisos/{$this->permisoA->id}")
        ->assertOk()
        ->assertJsonMissing(['observacion' => 'Motivo reservado']);

    $this->getJson("/api/v1/asistencia/permisos/{$this->permisoB->id}")->assertForbidden();
});

it('el jefe inmediato que el permiso nombra lo ve aunque sea de otra unidad', function () {
    $dirigido = jpPermiso($this->servidorB, 'PER-JP-NOMBRA', jefe: $this->jefeA);

    expect(jpFolios($this->usuarioJefeA))->toBe(['PER-JP-A', 'PER-JP-NOMBRA']);

    $this->getJson("/api/v1/asistencia/permisos/{$dirigido->id}")->assertOk();
});

it('el subrogante ve los permisos de la unidad que subroga', function () {
    jpSubrogacion($this->subordinadoA, $this->jefaturaB, $this->jefeB);

    expect(jpFolios(jpUsuario($this->subordinadoA)))->toBe(['PER-JP-A', 'PER-JP-B'])
        // Su propio permiso lo ve siempre; el del otro de B, por la subrogación.
        ->and(jpFolios(jpUsuario($this->servidorB)))->toBe(['PER-JP-B']);
});

it('quien dejó la jefatura deja de ver a su equipo', function () {
    ContratoServidor::where('servidor_id', $this->jefeA->id)->update(['estado' => 'terminado']);
    $exJefe = $this->usuarioJefeA->fresh();

    expect(jpFolios($exJefe))->toBe([]);

    $this->getJson("/api/v1/asistencia/permisos/{$this->permisoA->id}")->assertForbidden();
});

// ── Vacaciones ───────────────────────────────────────────────────────

it('el jefe por su puesto lista las vacaciones y el saldo de su unidad, y no los de otra', function () {
    $deA = jpVacacion($this->subordinadoA);
    jpVacacion($this->servidorB);

    $ids = collect($this->actingAs($this->usuarioJefeA, 'sanctum')
        ->getJson('/api/v1/asistencia/vacaciones?per_page=50')
        ->assertOk()
        ->json('datos.data'))->pluck('id')->all();

    expect($ids)->toBe([$deA->id]);

    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$this->subordinadoA->id}")->assertOk();
    $this->getJson("/api/v1/asistencia/vacaciones/saldo/{$this->servidorB->id}")->assertForbidden();
});

it('sin jefatura ni rol de jefe no hay listado de vacaciones', function () {
    $this->actingAs(jpUsuario($this->subordinadoA), 'sanctum')
        ->getJson('/api/v1/asistencia/vacaciones')
        ->assertForbidden();
});

// ── Sesión y revisión ────────────────────────────────────────────────

it('la sesión dice qué unidades dirige', function () {
    $this->actingAs($this->usuarioJefeA, 'sanctum')
        ->getJson('/api/v1/auth/perfil')
        ->assertOk()
        ->assertJsonPath('datos.servidor.jefaturas', [
            ['unidad_id' => $this->unidadA->id, 'unidad' => 'Dirección A', 'como' => 'titular'],
        ]);

    $this->actingAs(jpUsuario($this->subordinadoA), 'sanctum')
        ->getJson('/api/v1/auth/perfil')
        ->assertJsonPath('datos.servidor.jefaturas', []);
});

it('el comando lista lo que hay que corregir antes de retirar el rol', function () {
    $sinJefatura = unidadDePrueba(['nombre' => 'Unidad sin jefatura']);
    puestoDePrueba($sinJefatura);
    $vacante = unidadDePrueba(['nombre' => 'Unidad vacante']);
    puestoJefeDePrueba($vacante);

    $conRol = User::factory()->create(['usuario_ti' => 'jefe.sin.puesto', 'servidor_id' => $this->subordinadoA->id]);
    $conRol->assignRole('jefe-unidad');
    $this->usuarioJefeA->update(['usuario_ti' => 'jefe.bien.puesto']);
    $this->usuarioJefeA->assignRole('jefe-unidad');

    $this->artisan('sgth:jefaturas:revisar')
        ->expectsOutputToContain('Unidad sin jefatura')
        ->expectsOutputToContain('Unidad vacante')
        ->expectsOutputToContain('jefe.sin.puesto')
        ->doesntExpectOutputToContain('jefe.bien.puesto')
        ->assertSuccessful();
});
