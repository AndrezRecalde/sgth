<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\TecnicoDtic;
use App\Models\Helpdesk\Ticket;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\ContratoVigencia;
use App\Models\InventarioTi\CronogramaVigencia;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

/*
| El tablero de Inicio de Tecnologías (docs/diseno-subsistema-tic.md, fase
| 4.1): cada bloque con su permiso, las cifras del mes de la mesa de ayuda,
| los equipos por estado, unidad y antigüedad, los contratos por vencer y los
| préstamos. El reloj queda fijo un miércoles a las 09:00.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    BienInformatico::unguard();
    PrestamoEquipo::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));

    $this->legal = unidadDePrueba(['nombre' => 'Asesoría Legal']);
    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->paola = Servidor::create([
        'cedula' => '0809090901', 'nombre' => 'Paola', 'apellido' => 'Benavides', 'unidad_administrativa_id' => $this->legal->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $usuario = function (?Servidor $s, string $rol) {
        $u = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $s?->id]);
        $u->assignRole($rol);

        return $u;
    };
    $this->usuarioPaola = $usuario($this->paola, 'servidor');
    $this->tecnico = $usuario(null, 'tecnico-dtic');
    $this->jefe = $usuario(null, 'jefe-tic');
    $this->auditor = $usuario(null, 'auditor');

    $this->computador = TipoBien::create(['nombre' => 'Computador', 'anios_vida_util' => 5]);
    $this->dell = Marca::create(['nombre' => 'DELL']);
});

function tablero(User $quien): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->getJson('/api/v1/tic/tablero');
}

function equipoDeTablero(array $atributos = []): BienInformatico
{
    static $n = 7000;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => (string) $n,
        'tipo_bien_id' => test()->computador->id, 'marca_id' => test()->dell->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ], $atributos));
}

function ticketDeTablero(array $datos = []): int
{
    return test()->actingAs(test()->usuarioPaola, 'sanctum')->postJson('/api/v1/helpdesk/tickets', array_merge([
        'categoria_id' => CategoriaTicket::where('nombre', 'Red e internet')->sole()->id,
        'asunto' => 'No tengo internet', 'descripcion' => 'Desde la mañana la computadora no navega.',
    ], $datos))->assertCreated()->json('datos.id');
}

test('cada bloque sale con su permiso', function () {
    tablero($this->usuarioPaola)->assertForbidden();

    $auditor = tablero($this->auditor)->assertOk();
    expect($auditor->json('datos.tickets'))->toBeNull()
        ->and($auditor->json('datos.equipos'))->not->toBeNull()
        ->and($auditor->json('datos.prestamos'))->not->toBeNull()
        // Resolver bajas no es del auditor.
        ->and($auditor->json('datos.equipos.bajas_por_resolver'))->toBeNull();

    $jefe = tablero($this->jefe)->assertOk();
    expect($jefe->json('datos.tickets.alcance'))->toBe('todos')
        ->and($jefe->json('datos.equipos.bajas_por_resolver'))->toBe(0);
});

test('la mesa de ayuda: lo que hay en curso, la cola del técnico y las cifras del mes', function () {
    $resuelto = ticketDeTablero(['asunto' => 'La impresora no imprime']);
    $suyo = ticketDeTablero(['asunto' => 'El correo no sincroniza']);
    $sinAsignar = ticketDeTablero();
    $paso = fn (User $u, int $id, string $p, array $d = []) => $this->actingAs($u, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/{$p}", $d)->assertOk();

    $paso($this->jefe, $resuelto, 'asignar', ['tecnico_id' => $this->tecnico->id]);
    $paso($this->tecnico, $resuelto, 'tomar');
    $this->travel(2)->hours();
    $paso($this->tecnico, $resuelto, 'resolver', ['solucion' => 'Se reinstaló el controlador de la impresora.']);
    $paso($this->usuarioPaola, $resuelto, 'confirmar', ['calificacion' => 4]);
    $paso($this->jefe, $suyo, 'asignar', ['tecnico_id' => $this->tecnico->id]);
    // Uno vencido: el plazo de resolución ya pasó y no espera al usuario.
    Ticket::whereKey($sinAsignar)->update(['fecha_vencimiento_sla' => now()->subHour()]);

    $jefe = tablero($this->jefe)->assertOk()->json('datos.tickets');
    expect($jefe['por_estado'])->toBe(['abierto' => 1, 'asignado' => 1, 'en_proceso' => 0, 'pendiente_usuario' => 0])
        ->and($jefe['sin_asignar'])->toBe(1)
        ->and($jefe['vencidos'])->toBe(1)
        ->and($jefe['mes'])->toMatchArray(['abiertos' => 3, 'resueltos' => 1, 'cumplimiento_sla' => 100, 'horas_resolucion' => 2.0, 'satisfaccion' => 4.0, 'encuestas' => 1])
        ->and($jefe['por_categoria'])->toBe([['categoria' => 'Red e internet', 'total' => 2]]);

    $tecnico = tablero($this->tecnico)->assertOk()->json('datos.tickets');
    expect($tecnico['alcance'])->toBe('propios')
        ->and($tecnico['mios'])->toBe(1)
        ->and(collect($tecnico['mi_cola'])->pluck('id')->all())->toBe([$suyo])
        ->and($tecnico['mes']['resueltos'])->toBe(1);
});

test('los equipos por estado, por unidad de quien los tiene y por antigüedad', function () {
    $this->actingAs($this->tecnico, 'sanctum');
    $enUso = equipoDeTablero(['unidad_administrativa_id' => $this->obras->id, 'fecha_compra' => '2024-03-01']);
    $this->postJson('/api/v1/inventario/asignaciones', [
        'bien_informatico_id' => $enUso->id, 'servidor_id' => $this->paola->id, 'fecha_asignacion' => now()->toDateString(),
    ])->assertCreated();
    equipoDeTablero(['unidad_administrativa_id' => $this->obras->id, 'fecha_compra' => '2017-01-15', 'fecha_fin_vida_util' => '2022-01-15']);
    equipoDeTablero(['estado_operativo' => 'en_mantenimiento']);
    equipoDeTablero(['estado_operativo' => 'dado_de_baja', 'fecha_compra' => '2010-01-01']);
    // Un monitor registrado como componente se cuenta con su computador.
    equipoDeTablero(['bien_padre_id' => $enUso->id]);

    $equipos = tablero($this->auditor)->assertOk()->json('datos.equipos');

    expect($equipos['en_servicio'])->toBe(3)
        ->and($equipos['por_estado'])->toMatchArray(['en_uso' => 1, 'disponible' => 1, 'en_mantenimiento' => 1, 'dado_de_baja' => 1])
        // El que tiene Paola cuenta para Asesoría Legal, aunque el equipo diga Obras.
        ->and($equipos['por_unidad'])->toBe([
            ['unidad_id' => $this->legal->id, 'unidad' => 'Asesoría Legal', 'total' => 1],
            ['unidad_id' => $this->obras->id, 'unidad' => 'Dirección de Obras', 'total' => 1],
            ['unidad_id' => null, 'unidad' => 'Sin unidad', 'total' => 1],
        ])
        ->and($equipos['antiguedad'])->toBe(['menos_de_3' => 1, 'de_3_a_5' => 0, 'de_5_a_8' => 0, 'mas_de_8' => 1, 'sin_fecha' => 1])
        ->and($equipos['vida_util_cumplida'])->toBe(1);
});

test('los contratos de vigencia por vencer y las próximas ventanas del proveedor', function () {
    $porVencer = ContratoVigencia::create(['proceso' => 'SIE-GADPE-018-2021', 'proveedor' => 'Compusoft', 'fecha_inicio' => '2021-11-01', 'fecha_fin' => '2026-11-13']);
    ContratoVigencia::create(['proceso' => 'SIE-GADPE-002-2024', 'fecha_inicio' => '2024-01-01', 'fecha_fin' => '2027-12-31']);
    equipoDeTablero(['contrato_vigencia_id' => $porVencer->id]);
    CronogramaVigencia::create(['contrato_vigencia_id' => $porVencer->id, 'fecha_desde' => '2026-10-20', 'fecha_hasta' => '2026-10-24', 'descripcion' => 'Segundo preventivo']);
    CronogramaVigencia::create(['contrato_vigencia_id' => $porVencer->id, 'fecha_desde' => '2026-04-01', 'fecha_hasta' => '2026-04-05']);

    $equipos = tablero($this->auditor)->assertOk()->json('datos.equipos');

    expect($equipos['contratos_por_vencer'])->toHaveCount(1)
        ->and($equipos['contratos_por_vencer'][0])->toMatchArray(['proceso' => 'SIE-GADPE-018-2021', 'dias' => 30, 'equipos' => 1])
        ->and($equipos['ventanas_proximas'])->toHaveCount(1)
        ->and($equipos['ventanas_proximas'][0])->toMatchArray(['desde' => '2026-10-20', 'descripcion' => 'Segundo preventivo']);
});

test('los préstamos: el resumen, los vencidos y las entregas de hoy', function () {
    $prestamo = fn (string $folio, string $estado, string $inicio, string $fin) => PrestamoEquipo::create([
        'folio' => $folio, 'servidor_id' => $this->paola->id, 'estado' => $estado, 'motivo' => 'Presentación',
        'lugar_uso' => 'Sala de sesiones', 'inicio_previsto' => $inicio, 'fin_previsto' => $fin,
    ]);
    $prestamo('PRE-2026-00001', 'en_curso', '2026-10-13 09:00', '2026-10-13 17:00');
    $prestamo('PRE-2026-00002', 'reservado', '2026-10-14 15:00', '2026-10-14 17:00');
    $prestamo('PRE-2026-00003', 'reservado', '2026-10-16 09:00', '2026-10-16 12:00');

    $prestamos = tablero($this->auditor)->assertOk()->json('datos.prestamos');

    expect($prestamos)->toMatchArray(['reservados' => 2, 'en_curso' => 1, 'vencidos' => 1])
        ->and(collect($prestamos['lista_vencidos'])->pluck('folio')->all())->toBe(['PRE-2026-00001'])
        ->and(collect($prestamos['entregas_hoy'])->pluck('folio')->all())->toBe(['PRE-2026-00002'])
        ->and($prestamos['entregas_hoy'][0]['servidor'])->toBe('Paola Benavides');
});

test('la carga de trabajo del técnico ya no es un número fijo', function () {
    $ficha = TecnicoDtic::create(['user_id' => $this->tecnico->id, 'nivel' => 1]);
    $id = ticketDeTablero();
    $this->actingAs($this->jefe, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/asignar", ['tecnico_id' => $this->tecnico->id])->assertOk();

    $this->getJson("/api/v1/helpdesk/tecnicos/{$ficha->id}/carga-trabajo")->assertOk()
        ->assertJsonPath('datos.tickets_abiertos', 1)
        ->assertJsonPath('datos.tickets_cerrados_mes', 0)
        ->assertJsonPath('datos.promedio_resolucion_horas', null);
});
