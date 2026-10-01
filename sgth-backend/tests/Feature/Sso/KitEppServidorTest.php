<?php

/*
| El kit de EPP de un servidor: lo que el puesto requiere Y lo que ya se le
| entregó.
|
| El endpoint devolvía el requerimiento del puesto a secas y el modal lo
| premarcaba entero, siempre. Entregar el mismo kit dos veces creaba filas
| duplicadas en `epp_entregas` sin un aviso. La comprobación manual que quedó
| pendiente en `docs/pendientes-sso.md` —«los equipos entregados ya no deben
| aparecer pendientes»— no estaba implementada: invalidar la caché del kit no
| podía arreglarla, porque este endpoint no tenía noción de «pendiente».
*/

use App\Enums\MotivoEntregaEpp;
use App\Models\Expediente\Servidor;
use App\Models\Sso\EppEntrega;
use App\Models\Sso\EquipoProteccion;
use App\Models\Sso\PuestoEpp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->gestor = User::create([
        'email' => 'epp@gadpe.gob.ec',
        'usuario_ti' => 'epp_gestor',
        'password' => bcrypt('secreto'),
        'primer_login' => false,
        'activo' => true,
    ]);
    $this->gestor->assignRole('admin-uath');

    $this->unidad = unidadDePrueba();
    $this->puesto = puestoDePrueba($this->unidad, 'Operador de maquinaria');

    $this->servidor = Servidor::create([
        'cedula' => '0800000055',
        'nombre' => 'Ana',
        'apellido' => 'Quiñónez',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id,
        'estado' => true,
    ]);

    $this->casco = EquipoProteccion::create([
        'codigo' => 'EPP-001', 'nombre' => 'Casco', 'tipo' => 'craneal',
        'vida_util_meses' => 12, 'estado' => true,
    ]);
    $this->guantes = EquipoProteccion::create([
        'codigo' => 'EPP-002', 'nombre' => 'Guantes de nitrilo', 'tipo' => 'manos',
        'vida_util_meses' => 24, 'estado' => true,
    ]);
});

function requerir(EquipoProteccion $equipo, ?int $frecuenciaMeses = null): PuestoEpp
{
    return PuestoEpp::create([
        'puesto_id' => test()->puesto->id,
        'equipo_proteccion_id' => $equipo->id,
        'cantidad_requerida' => 1,
        'frecuencia_reposicion_meses' => $frecuenciaMeses,
    ]);
}

function entregar(EquipoProteccion $equipo, string $fecha, MotivoEntregaEpp $motivo = MotivoEntregaEpp::ENTREGA): EppEntrega
{
    return EppEntrega::create([
        'servidor_id' => test()->servidor->id,
        'equipo_proteccion_id' => $equipo->id,
        'fecha_entrega' => $fecha,
        'cantidad' => 1,
        'motivo' => $motivo,
        'entregado_por' => test()->gestor->id,
    ]);
}

function kitDelServidor(): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->gestor, 'sanctum')
        ->getJson('/api/v1/sso/servidores/'.test()->servidor->id.'/kit-epp');
}

// ── Lo que el endpoint no sabía ───────────────────────────────────────

test('un equipo nunca entregado sale pendiente', function () {
    requerir($this->casco, 6);

    kitDelServidor()
        ->assertOk()
        ->assertJsonPath('datos.0.estado_kit', 'pendiente')
        ->assertJsonPath('datos.0.ultima_entrega', null);
});

test('un equipo entregado dentro de su plazo sale vigente y no pendiente', function () {
    // Es el duplicado que el modal provocaba: esto salía premarcado otra vez.
    requerir($this->casco, 6);
    entregar($this->casco, now()->subMonth()->toDateString());

    kitDelServidor()
        ->assertOk()
        ->assertJsonPath('datos.0.estado_kit', 'vigente')
        ->assertJsonPath('datos.0.ultima_entrega', now()->subMonth()->toDateString());
});

test('un equipo con el plazo cumplido vuelve a tocar', function () {
    // Y este es el motivo de no quedarse en «entregado alguna vez»: unas botas
    // de hace tres años tienen que volver a aparecer.
    requerir($this->casco, 6);
    entregar($this->casco, now()->subMonths(7)->toDateString());

    kitDelServidor()
        ->assertOk()
        ->assertJsonPath('datos.0.estado_kit', 'por_reponer');
});

test('el día en que toca reponer viaja a la pantalla', function () {
    // «Vigente» sin decir hasta cuándo no le sirve a quien decide si entrega.
    requerir($this->casco, 6);
    entregar($this->casco, '2026-04-15');

    kitDelServidor()
        ->assertOk()
        ->assertJsonPath('datos.0.reponer_desde', '2026-10-15');
});

// ── De dónde sale el plazo ────────────────────────────────────────────

test('sin frecuencia del puesto se usa la vida útil del equipo', function () {
    requerir($this->casco); // el casco dura 12 meses
    entregar($this->casco, now()->subMonths(13)->toDateString());

    kitDelServidor()->assertOk()->assertJsonPath('datos.0.estado_kit', 'por_reponer');
});

test('la frecuencia del puesto manda sobre la vida útil del equipo', function () {
    requerir($this->guantes, 3); // el equipo dura 24 meses; este puesto los repone cada 3
    entregar($this->guantes, now()->subMonths(4)->toDateString());

    kitDelServidor()->assertOk()->assertJsonPath('datos.0.estado_kit', 'por_reponer');
});

// ── Qué cuenta como entrega ───────────────────────────────────────────

test('una reposición cuenta como entrega para el plazo', function () {
    requerir($this->casco, 6);
    entregar($this->casco, now()->subMonths(10)->toDateString());
    entregar($this->casco, now()->subMonth()->toDateString(), MotivoEntregaEpp::REPOSICION);

    kitDelServidor()->assertOk()->assertJsonPath('datos.0.estado_kit', 'vigente');
});

test('una devolución no cuenta como entrega', function () {
    // Devolver no es recibir: si contara, devolver el casco lo dejaría
    // «vigente» y el servidor se quedaría sin casco y sin aviso.
    requerir($this->casco, 6);
    entregar($this->casco, now()->subDays(2)->toDateString(), MotivoEntregaEpp::DEVOLUCION);

    kitDelServidor()->assertOk()->assertJsonPath('datos.0.estado_kit', 'pendiente');
});

test('manda la última entrega y no la primera', function () {
    requerir($this->casco, 6);
    entregar($this->casco, now()->subMonths(20)->toDateString());
    entregar($this->casco, now()->subMonths(2)->toDateString());

    kitDelServidor()->assertOk()->assertJsonPath('datos.0.estado_kit', 'vigente');
});

test('la entrega a otro servidor no cuenta', function () {
    $otro = Servidor::create([
        'cedula' => '0800000056', 'nombre' => 'Beto', 'apellido' => 'Zeta',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puesto->id, 'estado' => true,
    ]);

    requerir($this->casco, 6);
    EppEntrega::create([
        'servidor_id' => $otro->id,
        'equipo_proteccion_id' => $this->casco->id,
        'fecha_entrega' => now()->toDateString(),
        'cantidad' => 1,
        'motivo' => MotivoEntregaEpp::ENTREGA,
        'entregado_por' => $this->gestor->id,
    ]);

    kitDelServidor()->assertOk()->assertJsonPath('datos.0.estado_kit', 'pendiente');
});

// ── Varios equipos a la vez ───────────────────────────────────────────

test('cada equipo del kit lleva su propio estado', function () {
    requerir($this->casco, 6);
    requerir($this->guantes, 6);
    entregar($this->casco, now()->subMonth()->toDateString());

    $datos = collect(kitDelServidor()->assertOk()->json('datos'))
        ->keyBy('equipo_proteccion_id');

    expect($datos[$this->casco->id]['estado_kit'])->toBe('vigente');
    expect($datos[$this->guantes->id]['estado_kit'])->toBe('pendiente');
});

// ── Los bordes que ya existían ────────────────────────────────────────

test('un servidor sin puesto no tiene kit', function () {
    $this->servidor->update(['puesto_id' => null]);

    kitDelServidor()->assertOk()->assertJsonCount(0, 'datos');
});

test('un puesto sin EPP requerido devuelve el kit vacío', function () {
    kitDelServidor()->assertOk()->assertJsonCount(0, 'datos');
});

// ── El duplicado dentro de una misma entrega ──────────────────────────

test('el mismo equipo repetido en el kit se rechaza', function () {
    // Sin `distinct`, el arreglo `equipos` con el mismo id dos veces creaba
    // dos filas del mismo equipo al mismo servidor el mismo día.
    requerir($this->casco, 6);

    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/sso/epp-entregas/kit', [
            'servidor_id' => $this->servidor->id,
            'fecha_entrega' => now()->toDateString(),
            'equipos' => [
                ['equipo_proteccion_id' => $this->casco->id, 'cantidad' => 1],
                ['equipo_proteccion_id' => $this->casco->id, 'cantidad' => 2],
            ],
        ])
        ->assertStatus(422);

    expect(EppEntrega::count())->toBe(0);
});

test('equipos distintos en el mismo kit se registran', function () {
    requerir($this->casco, 6);
    requerir($this->guantes, 6);

    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/sso/epp-entregas/kit', [
            'servidor_id' => $this->servidor->id,
            'fecha_entrega' => now()->toDateString(),
            'equipos' => [
                ['equipo_proteccion_id' => $this->casco->id, 'cantidad' => 1],
                ['equipo_proteccion_id' => $this->guantes->id, 'cantidad' => 2],
            ],
        ])
        ->assertCreated();

    expect(EppEntrega::count())->toBe(2);
});

test('entregado el kit, el mismo kit ya no sale pendiente', function () {
    // El recorrido de la comprobación manual pendiente: entregar, volver a
    // pedir el kit, y que no vuelva a ofrecerse.
    requerir($this->casco, 6);
    requerir($this->guantes, 6);

    $this->actingAs($this->gestor, 'sanctum')
        ->postJson('/api/v1/sso/epp-entregas/kit', [
            'servidor_id' => $this->servidor->id,
            'fecha_entrega' => now()->toDateString(),
            'equipos' => [
                ['equipo_proteccion_id' => $this->casco->id, 'cantidad' => 1],
                ['equipo_proteccion_id' => $this->guantes->id, 'cantidad' => 1],
            ],
        ])
        ->assertCreated();

    $estados = collect(kitDelServidor()->assertOk()->json('datos'))->pluck('estado_kit');

    expect($estados->all())->toBe(['vigente', 'vigente']);
});
