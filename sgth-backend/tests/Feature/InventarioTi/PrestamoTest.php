<?php

use App\Enums\EstadoBien;
use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\InventarioTi\PrestamoItem;
use App\Models\InventarioTi\ReglasPrestamo;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
| El préstamo de equipos (docs/diseno-subsistema-tic.md, 4.6; fase 2.1): la
| solicitud desde el Portal, el trámite de TIC, el préstamo directo, las reglas
| y la disponibilidad. El reloj queda fijo un miércoles a las 09:00.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-14 09:00'));
    Servidor::unguard();
    BienInformatico::unguard();
    $this->seed(RolPermisoSeeder::class);

    $servidor = fn (string $cedula, string $apellido) => Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => $apellido,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $usuario = function (?Servidor $s, string $rol) {
        $u = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $s?->id]);
        $u->assignRole($rol);

        return $u;
    };

    $this->paola = $servidor('0808888881', 'Benavides');
    $this->diego = $servidor('0808888882', 'Luzuriaga');
    $this->usuarioPaola = $usuario($this->paola, 'servidor');
    $this->usuarioDiego = $usuario($this->diego, 'servidor');
    $this->tecnico = $usuario(null, 'tecnico-dtic');

    $tipo = TipoBien::create(['nombre' => 'Proyector']);
    $marca = Marca::create(['nombre' => 'EPSON']);
    $equipo = fn (string $codigo, array $extra = []) => BienInformatico::create([
        'codigo_qr' => "{$codigo}-QR", 'codigo_institucional' => $codigo, 'tipo_bien_id' => $tipo->id,
        'marca_id' => $marca->id, 'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno', 'prestable' => true,
        ...$extra,
    ]);
    $this->proyector = $equipo('002104');
    $this->parlante = $equipo('005130');
    $this->noPrestable = $equipo('001774', ['prestable' => false]);
});

afterEach(fn () => Carbon::setTestNow());

function pedirPrestamo(User $quien, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->postJson('/api/v1/autoservicio/mis-prestamos', array_merge([
        'bienes'          => [test()->proyector->id],
        'inicio_previsto' => '2026-10-14 10:00',
        'fin_previsto'    => '2026-10-14 12:00',
        'motivo'          => 'Presentación del plan operativo.',
        'lugar_uso'       => 'Sala de sesiones, piso 3',
    ], $datos));
}

function pasoPrestamo(int $id, string $paso, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->tecnico, 'sanctum')->postJson("/api/v1/inventario/prestamos/{$id}/{$paso}", $datos);
}

function entregaPrestamo(int $id, string $cuando = '2026-10-14 09:00'): \Illuminate\Testing\TestResponse
{
    $items = PrestamoItem::where('prestamo_equipo_id', $id)->pluck('id')
        ->map(fn ($i) => ['id' => $i, 'accesorios' => 'Cable HDMI y control', 'condicion_entrega' => 'bueno'])->all();

    return pasoPrestamo($id, 'entregar', ['entregado_en' => $cuando, 'items' => $items]);
}

test('el servidor pide equipos concretos y la solicitud no ocupa el horario', function () {
    $datos = pedirPrestamo($this->usuarioPaola, ['bienes' => [$this->proyector->id, $this->parlante->id]])
        ->assertCreated()->json('datos');

    expect($datos['folio'])->toBe('PRE-2026-00001')
        ->and($datos['estado'])->toBe('solicitado')
        ->and($datos['cancelable'])->toBeTrue()
        ->and(collect($datos['items'])->pluck('bien.codigo_institucional')->all())->toBe(['002104', '005130'])
        ->and(PrestamoItem::where('bloquea', true)->count())->toBe(0);

    // Otra persona puede pedir lo mismo a la misma hora.
    expect(pedirPrestamo($this->usuarioDiego)->assertCreated()->json('datos.folio'))->toBe('PRE-2026-00002');
});

test('el horario sigue las reglas de TIC', function () {
    $error = fn (array $datos, string $campo) => pedirPrestamo($this->usuarioPaola, $datos)
        ->assertStatus(422)->assertJsonStructure(['errores' => [$campo]]);

    $error(['inicio_previsto' => '2026-10-14 08:00'], 'inicio_previsto');                                   // ya pasó
    $error(['inicio_previsto' => '2026-10-14 18:00', 'fin_previsto' => '2026-10-14 19:00'], 'inicio_previsto'); // fuera de atención
    $error(['inicio_previsto' => '2026-10-17 10:00', 'fin_previsto' => '2026-10-17 12:00'], 'inicio_previsto'); // sábado
    $error(['fin_previsto' => '2026-10-21 12:00'], 'fin_previsto');                                           // pasa de 5 días hábiles
    $error(['fin_previsto' => '2026-10-14 09:30'], 'fin_previsto');                                           // antes de la entrega

    // El quinto día hábil, contando el de la entrega, a la hora de cierre.
    pedirPrestamo($this->usuarioPaola, ['fin_previsto' => '2026-10-20 17:00'])->assertCreated();

    ReglasPrestamo::vigentes()->update(['anticipacion_minima_minutos' => 120]);
    $error(['inicio_previsto' => '2026-10-14 10:00', 'bienes' => [$this->parlante->id]], 'inicio_previsto');
});

test('solo se prestan equipos del grupo de préstamo que estén en TIC', function () {
    pedirPrestamo($this->usuarioPaola, ['bienes' => [$this->noPrestable->id]])
        ->assertStatus(422)->assertJsonStructure(['errores' => ['bienes']]);

    $this->proyector->update(['estado_operativo' => EstadoBien::EN_MANTENIMIENTO]);
    expect(pedirPrestamo($this->usuarioPaola)->assertStatus(422)->json('errores.bienes.0'))->toContain('en mantenimiento');
});

test('el mismo servidor no pide dos veces el mismo equipo para el mismo horario', function () {
    pedirPrestamo($this->usuarioPaola)->assertCreated();
    pedirPrestamo($this->usuarioPaola, ['inicio_previsto' => '2026-10-14 11:00', 'fin_previsto' => '2026-10-14 13:00'])
        ->assertStatus(422);
});

test('aprobar reserva el horario y la segunda solicitud del mismo horario ya no se aprueba', function () {
    $primera = pedirPrestamo($this->usuarioPaola)->json('datos.id');
    $segunda = pedirPrestamo($this->usuarioDiego, ['inicio_previsto' => '2026-10-14 11:00', 'fin_previsto' => '2026-10-14 13:00'])->json('datos.id');

    expect(pasoPrestamo($primera, 'aprobar')->assertOk()->json('datos.estado'))->toBe('reservado');
    expect(pasoPrestamo($segunda, 'aprobar')->assertStatus(422)->json('errores.bienes.0'))->toContain('002104');

    // Y ya no se puede pedir ese horario desde el Portal.
    expect(pedirPrestamo($this->usuarioPaola, ['inicio_previsto' => '2026-10-14 11:30', 'fin_previsto' => '2026-10-14 12:30'])
        ->assertStatus(422)->json('errores.bienes.0'))->toContain('ocupado');
    // Al lado, sí: el rango es semiabierto.
    pedirPrestamo($this->usuarioPaola, ['inicio_previsto' => '2026-10-14 12:00', 'fin_previsto' => '2026-10-14 13:00'])->assertCreated();
});

test('la base de datos no deja dos préstamos activos del mismo equipo en el mismo horario', function () {
    $prestamo = fn () => PrestamoEquipo::create([
        'folio' => 'T-' . random_int(100000, 999999), 'servidor_id' => $this->paola->id, 'estado' => 'reservado', 'motivo' => 'x',
        'lugar_uso' => 'x', 'inicio_previsto' => '2026-10-15 09:00', 'fin_previsto' => '2026-10-15 12:00',
    ]);
    $item = fn (PrestamoEquipo $p, string $inicio, string $fin) => PrestamoItem::create([
        'prestamo_equipo_id' => $p->id, 'bien_informatico_id' => $this->proyector->id,
        'inicio' => $inicio, 'fin' => $fin, 'bloquea' => true,
    ]);

    $item($prestamo(), '2026-10-15 09:00', '2026-10-15 12:00');
    $item($prestamo(), '2026-10-15 12:00', '2026-10-15 13:00'); // contiguo: no se pisa

    expect(fn () => $item($prestamo(), '2026-10-15 11:00', '2026-10-15 14:00'))->toThrow(QueryException::class);
});

test('entregar y recibir anotan la hora real, la condición de cada equipo y liberan el horario', function () {
    $id = pedirPrestamo($this->usuarioPaola)->json('datos.id');
    pasoPrestamo($id, 'aprobar')->assertOk();

    entregaPrestamo($id, '2026-10-14 10:00')->assertStatus(422)->assertJsonStructure(['errores' => ['entregado_en']]); // futura
    pasoPrestamo($id, 'entregar', ['entregado_en' => '2026-10-14 09:00', 'items' => []])->assertStatus(422);

    $datos = entregaPrestamo($id)->assertOk()->json('datos');
    expect($datos['estado'])->toBe('en_curso')
        ->and($datos['items'][0]['accesorios'])->toBe('Cable HDMI y control')
        ->and($datos['items'][0]['condicion_entrega'])->toBe('bueno');

    Carbon::setTestNow(Carbon::parse('2026-10-14 12:30'));
    $item = PrestamoItem::where('prestamo_equipo_id', $id)->value('id');
    $datos = pasoPrestamo($id, 'recibir', [
        'devuelto_en' => '2026-10-14 12:20',
        'items' => [['id' => $item, 'condicion_devolucion' => 'regular', 'novedad' => 'Rayón en la carcasa.']],
    ])->assertOk()->json('datos');

    expect($datos['estado'])->toBe('devuelto')
        ->and($datos['items'][0]['novedad'])->toBe('Rayón en la carcasa.')
        ->and($this->proyector->fresh()->condicion_fisica->value)->toBe('regular')
        ->and(PrestamoItem::where('bloquea', true)->count())->toBe(0);

    $historial = $this->actingAs($this->tecnico, 'sanctum')->getJson("/api/v1/inventario/prestamos/{$id}")->json('datos.historial');
    expect(collect($historial)->pluck('estado_nuevo')->all())->toBe(['solicitado', 'reservado', 'en_curso', 'devuelto']);
});

test('rechazar pide motivo y solo va sobre lo solicitado', function () {
    $id = pedirPrestamo($this->usuarioPaola)->json('datos.id');

    pasoPrestamo($id, 'rechazar', ['motivo' => ''])->assertStatus(422);
    expect(pasoPrestamo($id, 'rechazar', ['motivo' => 'El proyector se usa en el evento de la Prefectura.'])->assertOk()->json('datos.estado'))
        ->toBe('rechazado');

    pasoPrestamo($id, 'aprobar')->assertStatus(422);
});

test('el servidor cancela lo suyo mientras no se haya entregado', function () {
    $id = pedirPrestamo($this->usuarioPaola)->json('datos.id');
    pasoPrestamo($id, 'aprobar')->assertOk();

    $this->actingAs($this->usuarioDiego, 'sanctum')->postJson("/api/v1/autoservicio/mis-prestamos/{$id}/cancelar")->assertNotFound();
    $this->actingAs($this->usuarioDiego, 'sanctum')->getJson("/api/v1/autoservicio/mis-prestamos/{$id}")->assertNotFound();

    expect($this->actingAs($this->usuarioPaola, 'sanctum')->postJson("/api/v1/autoservicio/mis-prestamos/{$id}/cancelar")
        ->assertOk()->json('datos.estado'))->toBe('cancelado')
        ->and(PrestamoItem::where('bloquea', true)->count())->toBe(0);

    $otro = pedirPrestamo($this->usuarioPaola, ['bienes' => [$this->parlante->id]])->json('datos.id');
    pasoPrestamo($otro, 'aprobar')->assertOk();
    entregaPrestamo($otro)->assertOk();
    $this->actingAs($this->usuarioPaola, 'sanctum')->postJson("/api/v1/autoservicio/mis-prestamos/{$otro}/cancelar")->assertStatus(422);

    $mios = $this->actingAs($this->usuarioPaola, 'sanctum')->getJson('/api/v1/autoservicio/mis-prestamos')->assertOk()->json('datos');
    expect($mios)->toHaveCount(2);
});

test('con un préstamo vencido no se pide otro', function () {
    $id = pedirPrestamo($this->usuarioPaola)->json('datos.id');
    pasoPrestamo($id, 'aprobar')->assertOk();
    entregaPrestamo($id)->assertOk();

    Carbon::setTestNow(Carbon::parse('2026-10-14 13:00'));
    expect(pedirPrestamo($this->usuarioPaola, [
        'bienes' => [$this->parlante->id], 'inicio_previsto' => '2026-10-15 09:00', 'fin_previsto' => '2026-10-15 10:00',
    ])->assertStatus(422)->json('mensaje'))->toContain('vencido');

    // En la bandeja de TIC sale como vencido.
    $vencidos = $this->actingAs($this->tecnico, 'sanctum')->getJson('/api/v1/inventario/prestamos?vista=vencidos')->json('datos');
    expect($vencidos)->toHaveCount(1)->and($vencidos[0]['vencido'])->toBeTrue();

    ReglasPrestamo::vigentes()->update(['bloquea_con_vencido' => false]);
    pedirPrestamo($this->usuarioPaola, [
        'bienes' => [$this->parlante->id], 'inicio_previsto' => '2026-10-15 09:00', 'fin_previsto' => '2026-10-15 10:00',
    ])->assertCreated();
});

test('el préstamo directo se entrega en el acto y no presta lo que no ha vuelto', function () {
    $datos = $this->actingAs($this->tecnico, 'sanctum')->postJson('/api/v1/inventario/prestamos/directo', [
        'servidor_id'  => $this->diego->id,
        'items'        => [['bien_informatico_id' => $this->parlante->id, 'accesorios' => 'Cable', 'condicion_entrega' => 'bueno']],
        'fin_previsto' => '2026-10-14 11:00',
        'motivo'       => 'Reunión con la comunidad.',
        'lugar_uso'    => 'Auditorio',
    ])->assertCreated()->json('datos');

    expect($datos['estado'])->toBe('en_curso')->and($datos['directo'])->toBeTrue()
        ->and($datos['entregado_por'])->toBe($this->tecnico->nombre_completo)
        ->and($datos['items'][0]['condicion_entrega'])->toBe('bueno');

    // Vencido y sin devolver: su horario ya pasó, pero el equipo sigue fuera.
    Carbon::setTestNow(Carbon::parse('2026-10-14 15:00'));
    expect($this->actingAs($this->tecnico, 'sanctum')->postJson('/api/v1/inventario/prestamos/directo', [
        'servidor_id'  => $this->paola->id,
        'items'        => [['bien_informatico_id' => $this->parlante->id, 'condicion_entrega' => 'bueno']],
        'fin_previsto' => '2026-10-14 16:00',
        'motivo'       => 'Capacitación interna.',
        'lugar_uso'    => 'Sala 2',
    ])->assertStatus(422)->json('mensaje'))->toContain('Todavía no vuelve');
});

test('la disponibilidad del Portal no dice quién tiene el equipo; la de TIC sí', function () {
    $id = pedirPrestamo($this->usuarioPaola, ['inicio_previsto' => '2026-10-14 09:00'])->json('datos.id');
    pasoPrestamo($id, 'aprobar')->assertOk();
    entregaPrestamo($id)->assertOk();
    $reserva = pedirPrestamo($this->usuarioDiego, [
        'bienes' => [$this->parlante->id], 'inicio_previsto' => '2026-10-15 10:00', 'fin_previsto' => '2026-10-15 11:00',
    ])->json('datos.id');
    pasoPrestamo($reserva, 'aprobar')->assertOk();

    $portal = collect($this->actingAs($this->usuarioDiego, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-prestamos/disponibilidad?desde=2026-10-14&hasta=2026-10-16')
        ->assertOk()->json('datos'))->keyBy('codigo_institucional');

    expect($portal->keys()->all())->toBe(['002104', '005130'])
        ->and($portal['002104']['ahora']['estado'])->toBe('prestado')
        ->and($portal['002104']['ahora'])->not->toHaveKey('servidor')
        ->and($portal['005130']['ahora']['estado'])->toBe('disponible')
        ->and($portal['005130']['ahora']['proxima_reserva'])->not->toBeNull()
        ->and($portal['005130']['bloques'][0])->not->toHaveKey('folio');

    $tic = collect($this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/inventario/prestamos/disponibilidad?desde=2026-10-14&hasta=2026-10-16')
        ->assertOk()->json('datos'))->keyBy('codigo_institucional');
    expect($tic['002104']['ahora']['servidor'])->toBe('Benavides Ana')
        ->and($tic['005130']['bloques'][0]['folio'])->toBe('PRE-2026-00002');

    $this->actingAs($this->usuarioDiego, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-prestamos/disponibilidad?desde=2026-10-01&hasta=2026-12-31')->assertStatus(422);
});

test('el trámite es de quien gestiona préstamos; el auditor solo consulta', function () {
    $id = pedirPrestamo($this->usuarioPaola)->json('datos.id');

    $this->actingAs($this->usuarioDiego, 'sanctum')->postJson("/api/v1/inventario/prestamos/{$id}/aprobar")->assertForbidden();
    $this->actingAs($this->usuarioDiego, 'sanctum')->getJson('/api/v1/inventario/prestamos')->assertForbidden();

    $auditor = User::factory()->create(['primer_login' => false]);
    $auditor->assignRole('auditor');
    $this->actingAs($auditor, 'sanctum')->getJson('/api/v1/inventario/prestamos?vista=por_aprobar')->assertOk()->assertJsonCount(1, 'datos');
    $this->actingAs($auditor, 'sanctum')->postJson("/api/v1/inventario/prestamos/{$id}/aprobar")->assertForbidden();
});

test('TIC cambia las reglas y el Portal las lee', function () {
    $this->actingAs($this->tecnico, 'sanctum')->putJson('/api/v1/inventario/prestamos/reglas', [
        'plazo_maximo_dias_habiles' => 3, 'anticipacion_minima_minutos' => 30, 'hora_apertura' => '08:30',
        'hora_cierre' => '08:00', 'presta_fines_de_semana' => false, 'bloquea_con_vencido' => true, 'margen_retiro_minutos' => 45,
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['hora_cierre']]);

    $this->actingAs($this->tecnico, 'sanctum')->putJson('/api/v1/inventario/prestamos/reglas', [
        'plazo_maximo_dias_habiles' => 3, 'anticipacion_minima_minutos' => 30, 'hora_apertura' => '08:30',
        'hora_cierre' => '16:30', 'presta_fines_de_semana' => false, 'bloquea_con_vencido' => true, 'margen_retiro_minutos' => 45,
    ])->assertOk();

    expect($this->actingAs($this->usuarioPaola, 'sanctum')->getJson('/api/v1/autoservicio/mis-prestamos/reglas')->json('datos'))
        ->toMatchArray(['plazo_maximo_dias_habiles' => 3, 'hora_apertura' => '08:30', 'hora_cierre' => '16:30', 'margen_retiro_minutos' => 45]);

    $this->actingAs($this->usuarioPaola, 'sanctum')->putJson('/api/v1/inventario/prestamos/reglas', [])->assertForbidden();
});

test('un equipo reservado no sale del grupo de préstamo ni se da de baja', function () {
    $id = pedirPrestamo($this->usuarioPaola)->json('datos.id');
    pasoPrestamo($id, 'aprobar')->assertOk();

    $this->actingAs($this->tecnico, 'sanctum')->putJson("/api/v1/inventario/bienes/{$this->proyector->id}", ['prestable' => false])
        ->assertStatus(422);

    $jefe = User::factory()->create(['primer_login' => false]);
    $jefe->assignRole('jefe-tic');
    $baja = $this->actingAs($this->tecnico, 'sanctum')->postJson('/api/v1/inventario/bajas', [
        'bien_informatico_id' => $this->proyector->id, 'motivo' => 'obsolescencia',
        'diagnostico' => 'La lámpara ya no se consigue en el mercado.', 'fecha_informe' => '2026-10-14',
    ])->assertCreated()->json('datos.id');
    $this->actingAs($jefe, 'sanctum')->postJson("/api/v1/inventario/bajas/{$baja}/aprobar", [
        'fecha_baja' => '2026-10-14', 'memorando_numero' => 'M-1',
    ])->assertStatus(422);
});
