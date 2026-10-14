<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\Helpdesk\CategoriaTicket;
use App\Models\Helpdesk\TecnicoDtic;
use App\Models\InventarioTi\BienInformatico;
use App\Models\InventarioTi\MantenimientoBien;
use App\Models\InventarioTi\Marca;
use App\Models\InventarioTi\TipoBien;
use App\Models\InventarioTi\Ubicacion;
use App\Models\User;
use App\Services\Tic\Reportes\ReporteSatisfaccionService;
use App\Services\Tic\Reportes\ReporteTicketsService;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

/*
| Los reportes de Tecnologías (docs/diseno-subsistema-tic.md, fase 4.2): el
| inventario por unidad o ubicación, la obsolescencia, la mesa de ayuda por
| técnico y categoría y la satisfacción; en pantalla, Excel y PDF. El reloj
| queda fijo un miércoles a las 09:00.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Servidor::unguard();
    BienInformatico::unguard();
    MantenimientoBien::unguard();
    $this->seed(RolPermisoSeeder::class);
    $this->travelTo(now()->setDate(2026, 10, 14)->setTime(9, 0));

    $this->legal = unidadDePrueba(['nombre' => 'Asesoría Legal']);
    $this->obras = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->paola = Servidor::create([
        'cedula' => '0806060601', 'nombre' => 'Paola', 'apellido' => 'Benavides', 'unidad_administrativa_id' => $this->legal->id,
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => true,
    ]);
    $usuario = function (?Servidor $s, string $rol, array $extra = []) {
        $u = User::factory()->create(['primer_login' => false, 'activo' => true, 'servidor_id' => $s?->id, ...$extra]);
        $u->assignRole($rol);

        return $u;
    };
    $this->usuarioPaola = $usuario($this->paola, 'servidor');
    $this->tecnico = $usuario(null, 'tecnico-dtic');
    $this->jefe = $usuario(null, 'jefe-tic');
    $this->auditor = $usuario(null, 'auditor');

    $this->computador = TipoBien::create(['nombre' => 'Computador', 'anios_vida_util' => 5]);
    $this->impresora = TipoBien::create(['nombre' => 'Impresora', 'anios_vida_util' => 0]);
    $this->dell = Marca::create(['nombre' => 'DELL']);
    $this->piso2 = Ubicacion::create(['sede' => 'Edificio matriz', 'piso' => 'Piso 2']);
});

function equipoDeReporte(array $atributos = []): BienInformatico
{
    static $n = 8000;
    $n++;

    return BienInformatico::create(array_merge([
        'codigo_qr' => "{$n}-QR", 'codigo_institucional' => (string) $n,
        'tipo_bien_id' => test()->computador->id, 'marca_id' => test()->dell->id,
        'estado_operativo' => 'disponible', 'condicion_fisica' => 'bueno',
    ], $atributos));
}

function reporteTic(User $quien, string $reporte, array $parametros = []): \Illuminate\Testing\TestResponse
{
    return test()->actingAs($quien, 'sanctum')->get("/api/v1/tic/reportes/{$reporte}?" . http_build_query($parametros), ['Accept' => 'application/json']);
}

test('los del inventario son de quien lo consulta; los de la mesa de ayuda, del jefe', function () {
    reporteTic($this->usuarioPaola, 'inventario')->assertForbidden();
    reporteTic($this->auditor, 'inventario')->assertOk();
    reporteTic($this->auditor, 'obsolescencia')->assertOk();
    reporteTic($this->auditor, 'tickets', ['desde' => '2026-10-01', 'hasta' => '2026-10-14'])->assertForbidden();
    // El técnico atiende, pero el trabajo de cada uno lo mira el jefe.
    reporteTic($this->tecnico, 'satisfaccion', ['desde' => '2026-10-01', 'hasta' => '2026-10-14'])->assertForbidden();
    reporteTic($this->jefe, 'tickets', ['desde' => '2026-10-01', 'hasta' => '2026-10-14'])->assertOk();
    reporteTic($this->jefe, 'tickets', ['desde' => '2025-01-01', 'hasta' => '2026-10-14'])->assertStatus(422);
});

test('el inventario por unidad cuenta el equipo para la unidad de quien lo tiene', function () {
    $this->actingAs($this->tecnico, 'sanctum');
    $dePaola = equipoDeReporte(['unidad_administrativa_id' => $this->obras->id, 'ubicacion_id' => $this->piso2->id]);
    $this->postJson('/api/v1/inventario/asignaciones', [
        'bien_informatico_id' => $dePaola->id, 'servidor_id' => $this->paola->id, 'fecha_asignacion' => now()->toDateString(),
    ])->assertCreated();
    equipoDeReporte(['unidad_administrativa_id' => $this->obras->id]);
    equipoDeReporte();
    equipoDeReporte(['estado_operativo' => 'dado_de_baja', 'unidad_administrativa_id' => $this->obras->id]);
    equipoDeReporte(['bien_padre_id' => $dePaola->id]);

    $porUnidad = reporteTic($this->auditor, 'inventario')->assertOk()->json('datos');
    expect($porUnidad['total'])->toBe(3)
        ->and(collect($porUnidad['grupos'])->map(fn ($g) => [$g['nombre'], $g['total']])->all())
        ->toBe([['Asesoría Legal', 1], ['Dirección de Obras', 1], ['Sin unidad', 1]])
        ->and($porUnidad['grupos'][0]['equipos'][0]['custodio'])->toBe('Paola Benavides');

    $porUbicacion = reporteTic($this->auditor, 'inventario', ['agrupar' => 'ubicacion'])->json('datos');
    expect(collect($porUbicacion['grupos'])->map(fn ($g) => [$g['nombre'], $g['total']])->all())
        ->toBe([['Edificio matriz · Piso 2', 1], ['Sin ubicación', 2]]);

    $deObras = reporteTic($this->auditor, 'inventario', ['unidad_administrativa_id' => $this->obras->id])->json('datos');
    expect($deObras['total'])->toBe(1);
});

test('la obsolescencia: cumplida, próxima y sin fin de vida útil, con los correctivos del año', function () {
    $viejo = equipoDeReporte(['fecha_compra' => '2019-03-01', 'fecha_fin_vida_util' => '2024-03-01']);
    equipoDeReporte(['fecha_compra' => '2021-12-01', 'fecha_fin_vida_util' => '2026-12-01']);
    equipoDeReporte(['fecha_compra' => '2025-01-10', 'fecha_fin_vida_util' => '2030-01-10']);
    equipoDeReporte(['tipo_bien_id' => $this->impresora->id, 'fecha_compra' => '2015-01-01']);
    equipoDeReporte(['estado_operativo' => 'dado_de_baja', 'fecha_compra' => '2010-01-01', 'fecha_fin_vida_util' => '2015-01-01']);
    $correctivo = fn (string $fecha, array $extra = []) => MantenimientoBien::create([
        'bien_informatico_id' => $viejo->id, 'tipo_mantenimiento' => 'correctivo', 'fecha_mantenimiento' => $fecha,
        'descripcion' => 'Cambio de fuente', ...$extra,
    ]);
    $correctivo('2026-05-10');
    $correctivo('2026-08-02', ['anulado_en' => now()]);
    $correctivo('2025-01-05');

    $r = reporteTic($this->auditor, 'obsolescencia')->assertOk()->json('datos');

    expect($r['totales'])->toBe(['en_servicio' => 4, 'cumplida' => 1, 'proxima' => 1, 'sin_fecha' => 1])
        ->and(collect($r['equipos'])->pluck('situacion')->all())->toBe(['cumplida', 'proxima'])
        ->and($r['equipos'][0])->toMatchArray(['anios' => 7, 'correctivos' => 1])
        ->and(collect($r['resumen'])->firstWhere('tipo', 'Impresora'))->toMatchArray(['anios_vida_util' => null, 'sin_fecha' => 1]);
});

test('la mesa de ayuda por técnico y categoría, y la satisfacción con sus comentarios', function () {
    TecnicoDtic::create(['user_id' => $this->tecnico->id, 'nivel' => 1]);
    $otro = User::factory()->create(['primer_login' => false, 'activo' => true]);
    $otro->assignRole('tecnico-dtic');
    TecnicoDtic::create(['user_id' => $otro->id, 'nivel' => 1]);

    $abrir = fn (string $categoria) => $this->actingAs($this->usuarioPaola, 'sanctum')->postJson('/api/v1/helpdesk/tickets', [
        'categoria_id' => CategoriaTicket::where('nombre', $categoria)->sole()->id,
        'asunto' => 'Necesito ayuda', 'descripcion' => 'Algo no funciona desde la mañana.',
    ])->assertCreated()->json('datos.id');
    $paso = fn (User $u, int $id, string $p, array $d = []) => $this->actingAs($u, 'sanctum')->postJson("/api/v1/helpdesk/tickets/{$id}/{$p}", $d)->assertOk();

    $red = $abrir('Red e internet');
    $correo = $abrir('Correo institucional');
    foreach ([[$red, 5, 'Muy rápido, gracias.'], [$correo, 3, null]] as [$id, $nota, $comentario]) {
        $paso($this->jefe, $id, 'asignar', ['tecnico_id' => $this->tecnico->id]);
        $paso($this->tecnico, $id, 'tomar');
        $paso($this->tecnico, $id, 'resolver', ['solucion' => 'Se reconfiguró el equipo del usuario.']);
        $paso($this->usuarioPaola, $id, 'confirmar', array_filter(['calificacion' => $nota, 'comentario' => $comentario]));
    }

    $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-14'];
    $tickets = reporteTic($this->jefe, 'tickets', $rango)->assertOk()->json('datos');
    $porTecnico = collect($tickets['por_tecnico'])->keyBy('tecnico');
    expect($tickets['totales'])->toMatchArray(['abiertos' => 2, 'resueltos' => 2, 'satisfaccion' => 4.0])
        ->and($porTecnico[$this->tecnico->nombre_completo])->toMatchArray(['abiertos' => 2, 'resueltos' => 2, 'en_curso' => 0])
        // El otro técnico no tuvo tickets, pero figura con cero.
        ->and($porTecnico[$otro->nombre_completo])->toMatchArray(['abiertos' => 0, 'resueltos' => 0, 'satisfaccion' => null])
        ->and(collect($tickets['por_categoria'])->pluck('categoria')->all())->toBe(['Correo institucional', 'Red e internet']);

    $satisfaccion = reporteTic($this->jefe, 'satisfaccion', $rango)->assertOk()->json('datos');
    expect($satisfaccion)->toMatchArray(['encuestas' => 2, 'promedio' => 4.0])
        ->and($satisfaccion['distribucion'])->toBe(['5' => 1, '4' => 0, '3' => 1, '2' => 0, '1' => 0])
        ->and($satisfaccion['comentarios'])->toHaveCount(1)
        ->and($satisfaccion['comentarios'][0])->toMatchArray(['calificacion' => 5, 'comentario' => 'Muy rápido, gracias.']);

    // Los resultados de encuestas ya no son un 4.5 fijo.
    $this->actingAs($this->jefe, 'sanctum')->getJson('/api/v1/helpdesk/encuestas-satisfaccion/resultados')->assertOk()
        ->assertJsonPath('datos.promedio_general', 4)
        ->assertJsonPath('datos.encuestas', 2);
});

test('cada reporte se descarga en Excel y en PDF', function () {
    $viejo = equipoDeReporte(['fecha_compra' => '2019-03-01', 'fecha_fin_vida_util' => '2024-03-01']);
    $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-14'];

    foreach (['inventario' => [], 'obsolescencia' => [], 'tickets' => $rango, 'satisfaccion' => $rango] as $reporte => $parametros) {
        $pdf = reporteTic($this->jefe, $reporte, [...$parametros, 'formato' => 'pdf'])->assertOk();
        expect($pdf->headers->get('content-type'))->toBe('application/pdf')
            ->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
    }

    // Las secciones del PDF se recorrían con una variable `$titulo` que pisaba
    // el del documento: salía titulado «Por categoría».
    foreach (['tickets' => 'Mesa de ayuda por técnico y categoría', 'satisfaccion' => 'Satisfacción con la mesa de ayuda'] as $vista => $titulo) {
        $servicio = $vista === 'tickets' ? ReporteTicketsService::class : ReporteSatisfaccionService::class;
        $html = view("pdf.tic.{$vista}", [
            'reporte' => app($servicio)->generar(now()->startOfMonth(), now()), 'titulo' => $titulo, 'subtitulo' => '', 'logo' => '',
        ])->render();
        expect($html)->toContain("<h1>{$titulo}</h1>");
    }

    Excel::fake();
    reporteTic($this->jefe, 'obsolescencia', ['formato' => 'excel'])->assertOk();
    // Lo que se ve en pantalla es lo que se descarga: el resumen y los equipos.
    Excel::assertDownloaded('obsolescencia-2026-10-14.xlsx', fn ($libro) => count($libro->sheets()) === 2
        && $libro->sheets()[1]->array()[0][0] === $viejo->codigo_institucional);
    reporteTic($this->jefe, 'tickets', [...$rango, 'formato' => 'excel'])->assertOk();
    Excel::assertDownloaded('mesa-de-ayuda-2026-10-01-a-2026-10-14.xlsx', fn ($libro) => count($libro->sheets()) === 2);
});
