<?php

use App\Enums\RegimenLaboral;
use App\Mail\InventarioTi\PrestamoEquipoMail;
use App\Models\Expediente\Servidor;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\PrestamoEquipo;
use App\Models\InventarioTi\PrestamoItem;
use App\Models\InventarioTi\TipoBien;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/*
| Los avisos del préstamo de equipos y lo que pasa solo con el reloj (fase
| 2.4): los correos de cada paso, la reserva no retirada, el recordatorio, el
| vencido y el reporte de uso. Miércoles 14 de octubre de 2026, 09:00.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-14 09:00'));
    Mail::fake();
    Servidor::unguard();
    $this->seed(RolPermisoSeeder::class);

    $this->paola = Servidor::create([
        'cedula' => '0809999991', 'nombre' => 'Paola', 'apellido' => 'Benavides',
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $this->usuarioPaola = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $this->paola->id, 'email' => 'paola@gadpe.gob.ec']);
    $this->usuarioPaola->assignRole('servidor');
    $this->tecnico = User::factory()->create(['primer_login' => false, 'activo' => true, 'email' => 'tecnico@gadpe.gob.ec']);
    $this->tecnico->assignRole('tecnico-dtic');

    $this->proyector = BienInformatico::create([
        'codigo_qr' => '002104-QR', 'codigo_institucional' => '002104', 'prestable' => true,
        'tipo_bien_id' => TipoBien::create(['nombre' => 'Proyector'])->id, 'marca_id' => Marca::create(['nombre' => 'EPSON'])->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function pedirParaAvisos(array $datos = []): int
{
    return test()->actingAs(test()->usuarioPaola, 'sanctum')->postJson('/api/v1/autoservicio/mis-prestamos', array_merge([
        'bienes' => [test()->proyector->id], 'inicio_previsto' => '2026-10-14 10:00', 'fin_previsto' => '2026-10-14 12:00',
        'motivo' => 'Presentación del plan operativo.', 'lugar_uso' => 'Sala de sesiones',
    ], $datos))->assertCreated()->json('datos.id');
}

function pasoParaAvisos(int $id, string $paso, array $datos = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs(test()->tecnico, 'sanctum')->postJson("/api/v1/inventario/prestamos/{$id}/{$paso}", $datos);
}

function entregarParaAvisos(int $id): void
{
    $items = PrestamoItem::where('prestamo_equipo_id', $id)->pluck('id')->map(fn ($i) => ['id' => $i, 'condicion_entrega' => 'bueno'])->all();
    pasoParaAvisos($id, 'entregar', ['entregado_en' => now()->format('Y-m-d H:i'), 'items' => $items])->assertOk();
}

function avisosA(string $correo): array
{
    return Mail::sent(PrestamoEquipoMail::class)->filter(fn ($m) => $m->hasTo($correo))->map(fn ($m) => $m->aviso)->values()->all();
}

test('el servidor recibe un correo de cada paso que da TIC', function () {
    $id = pedirParaAvisos();
    pasoParaAvisos($id, 'aprobar')->assertOk();
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:05'));
    entregarParaAvisos($id);
    $item = PrestamoItem::where('prestamo_equipo_id', $id)->value('id');
    pasoParaAvisos($id, 'recibir', [
        'devuelto_en' => '2026-10-14 10:05', 'items' => [['id' => $item, 'condicion_devolucion' => 'bueno', 'novedad' => 'Sin el control']],
    ])->assertOk();

    expect(avisosA('paola@gadpe.gob.ec'))->toBe(['aprobado', 'entregado', 'devuelto']);

    $otro = pedirParaAvisos(['inicio_previsto' => '2026-10-15 10:00', 'fin_previsto' => '2026-10-15 11:00']);
    pasoParaAvisos($otro, 'rechazar', ['motivo' => 'El proyector va al evento de la Prefectura.'])->assertOk();
    expect(avisosA('paola@gadpe.gob.ec'))->toBe(['aprobado', 'entregado', 'devuelto', 'rechazado']);
});

test('quien cancela lo suyo no recibe correo; si cancela TIC, sí', function () {
    $propio = pedirParaAvisos();
    $this->actingAs($this->usuarioPaola, 'sanctum')->postJson("/api/v1/autoservicio/mis-prestamos/{$propio}/cancelar")->assertOk();
    expect(avisosA('paola@gadpe.gob.ec'))->toBe([]);

    $otro = pedirParaAvisos(['inicio_previsto' => '2026-10-15 10:00', 'fin_previsto' => '2026-10-15 11:00']);
    pasoParaAvisos($otro, 'cancelar', ['motivo' => 'El equipo pasa a mantenimiento.'])->assertOk();
    expect(avisosA('paola@gadpe.gob.ec'))->toBe(['cancelado']);
});

test('sin dirección de correo el paso se registra igual', function () {
    // Un servidor sin cuenta ni correo personal: lo atiende TIC en el mostrador.
    $sinCorreo = Servidor::create([
        'cedula' => '0809999992', 'nombre' => 'Omar', 'apellido' => 'Vega', 'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);

    $this->actingAs($this->tecnico, 'sanctum')->postJson('/api/v1/inventario/prestamos/directo', [
        'servidor_id' => $sinCorreo->id, 'items' => [['bien_informatico_id' => $this->proyector->id, 'condicion_entrega' => 'bueno']],
        'fin_previsto' => '2026-10-14 11:00', 'motivo' => 'Reunión con la comunidad.', 'lugar_uso' => 'Auditorio',
    ])->assertCreated()->assertJsonPath('datos.estado', 'en_curso');

    Mail::assertNothingSent();
});

test('la reserva no retirada dentro del margen se cierra sola y libera el horario', function () {
    $id = pedirParaAvisos();
    pasoParaAvisos($id, 'aprobar')->assertOk();

    // Margen de 60 minutos: a las 10:45 todavía se espera.
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:45'));
    $this->artisan('sgth:prestamos:seguimiento')->assertSuccessful();
    expect(PrestamoEquipo::find($id)->estado->value)->toBe('reservado');

    Carbon::setTestNow(Carbon::parse('2026-10-14 11:01'));
    $this->artisan('sgth:prestamos:seguimiento')->expectsOutputToContain('1 reserva(s) no retirada(s)')->assertSuccessful();

    expect(PrestamoEquipo::find($id)->estado->value)->toBe('no_retirado')
        ->and(PrestamoItem::where('bloquea', true)->count())->toBe(0)
        ->and(avisosA('paola@gadpe.gob.ec'))->toBe(['aprobado', 'no_retirado'])
        ->and(PrestamoEquipo::find($id)->historial->last()->detalle)->toContain('60 minutos');
});

test('el recordatorio sale una hora antes y una sola vez; el vencido, al servidor y a TIC una vez', function () {
    $id = pedirParaAvisos();
    pasoParaAvisos($id, 'aprobar')->assertOk();
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00'));
    entregarParaAvisos($id);

    Carbon::setTestNow(Carbon::parse('2026-10-14 10:30'));
    $this->artisan('sgth:prestamos:seguimiento')->assertSuccessful();
    expect(avisosA('paola@gadpe.gob.ec'))->not->toContain('recordatorio');

    Carbon::setTestNow(Carbon::parse('2026-10-14 11:05'));
    $this->artisan('sgth:prestamos:seguimiento')->assertSuccessful();
    $this->artisan('sgth:prestamos:seguimiento')->assertSuccessful();
    expect(collect(avisosA('paola@gadpe.gob.ec'))->filter(fn ($a) => $a === 'recordatorio'))->toHaveCount(1);

    Carbon::setTestNow(Carbon::parse('2026-10-14 12:10'));
    $this->artisan('sgth:prestamos:seguimiento')->assertSuccessful();
    $this->artisan('sgth:prestamos:seguimiento')->assertSuccessful();

    expect(collect(avisosA('paola@gadpe.gob.ec'))->filter(fn ($a) => $a === 'vencido'))->toHaveCount(1)
        ->and(avisosA('tecnico@gadpe.gob.ec'))->toBe(['vencido_tic']);
});

test('el correo dice lo que pasó y lleva al Portal', function () {
    $id = pedirParaAvisos();
    pasoParaAvisos($id, 'aprobar')->assertOk();

    $correo = new PrestamoEquipoMail(PrestamoEquipo::with('servidor', 'items.bien.tipo', 'items.bien.marca')->find($id), 'aprobado');
    $correo->assertSeeInHtml('PRE-2026-00001')
        ->assertSeeInHtml('002104')
        ->assertSeeInHtml('14/10/2026 12:00')
        ->assertSeeInHtml('/portal/mis-prestamos');
    expect($correo->envelope()->subject)->toContain('Su préstamo está aprobado');
});

test('el reporte de uso cuenta préstamos, horas, devoluciones tarde y reservas no retiradas', function () {
    $a = pedirParaAvisos();
    pasoParaAvisos($a, 'aprobar')->assertOk();
    Carbon::setTestNow(Carbon::parse('2026-10-14 10:00'));
    entregarParaAvisos($a);
    Carbon::setTestNow(Carbon::parse('2026-10-14 12:30'));
    $item = PrestamoItem::where('prestamo_equipo_id', $a)->value('id');
    pasoParaAvisos($a, 'recibir', ['devuelto_en' => '2026-10-14 12:30', 'items' => [['id' => $item, 'condicion_devolucion' => 'bueno']]])->assertOk();

    $b = pedirParaAvisos(['inicio_previsto' => '2026-10-15 09:00', 'fin_previsto' => '2026-10-15 10:00']);
    pasoParaAvisos($b, 'aprobar')->assertOk();
    Carbon::setTestNow(Carbon::parse('2026-10-15 10:30'));
    $this->artisan('sgth:prestamos:seguimiento')->assertSuccessful();

    $datos = $this->actingAs($this->tecnico, 'sanctum')
        ->getJson('/api/v1/inventario/prestamos/reporte-uso?desde=2026-10-01&hasta=2026-10-31')->assertOk()->json('datos');

    expect($datos['equipos'][0])->toMatchArray([
        'codigo_institucional' => '002104', 'prestamos' => 1, 'horas' => 2.5, 'devoluciones_tarde' => 1, 'no_retirados' => 1,
    ])->and($datos['totales']['prestamos'])->toBe(1);

    $this->actingAs($this->tecnico, 'sanctum')->get('/api/v1/inventario/prestamos/reporte-uso/excel?desde=2026-10-01&hasta=2026-10-31')
        ->assertOk()
        ->assertDownload('uso-equipos-prestamo-2026-10-01-a-2026-10-31.xlsx');
});
