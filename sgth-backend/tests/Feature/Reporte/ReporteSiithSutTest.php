<?php

namespace Tests\Feature\Reporte;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Reporte\ConfiguracionReporteMovimiento;
use App\Models\Reporte\TranscripcionReporte;
use App\Models\User;
use Database\Seeders\ConfiguracionReporteMovimientoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.5 del diseño de Acciones de Personal (9.12): el reporte de apoyo a la
| transcripción al SIITH y al SUT. Por clase, el portal por el instrumento del
| acto, lo registrado en el mes y lo que sigue sin transcribirse, y la
| anulación de lo ya transcrito.
|
| Hoy es el martes 20 de octubre de 2026.
*/
beforeEach(function () {
    foreach (['admin-uath', 'asistente-uath'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    permisosDeAccionesPersonal();
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->puesto = puestoDePrueba($this->unidad, 'Analista', ['plazas' => 10]);

    $this->contador = 0;
    /** Un acto ya registrado, con su clase y su instrumento. */
    $this->acto = function (string $tipo, string $instrumento, string $registro, array $mas = []): MovimientoPersonal {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (7800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Reporte'.$this->contador, 'regimen_laboral' => 'losep',
        ]);

        return MovimientoPersonal::create([
            'servidor_id' => $servidor->id, 'tipo_movimiento' => $tipo, 'instrumento' => $instrumento,
            'categoria' => CategoriaEventoVinculo::ACCION_DE_PERSONAL, 'estado' => EstadoAccionPersonal::REGISTRADA,
            'descripcion' => 'Acto de prueba.', 'fecha_efectiva' => $registro, 'fecha_registro' => $registro,
            'codigo_registro' => 'AP-2026-'.str_pad((string) $this->contador, 4, '0', STR_PAD_LEFT),
            'puesto_destino_id' => $this->puesto->id, 'unidad_destino_id' => $this->unidad->id,
            'autorizado_por' => $this->user->id,
            ...$mas,
        ]);
    };

    $this->reporte = fn (string $portal, string $periodo = '2026-10', string $situacion = 'pendientes') => $this
        ->getJson("/api/v1/reportes/siith-sut?portal={$portal}&periodo={$periodo}&situacion={$situacion}")
        ->assertOk()
        ->json('datos');
});

// ── Qué se reporta ──────────────────────────────────────────────

test('la configuración es por clase: al SIITH lo de la LOSEP, al SUT el ingreso y la terminación del obrero', function () {
    $configuracion = collect($this->getJson('/api/v1/reportes/siith-sut/configuracion')->assertOk()->json('datos'))->keyBy('clase');

    expect($configuracion)->toHaveCount(23)
        ->and($configuracion['cesacion']['reportable_siith'])->toBeTrue()
        ->and($configuracion['cesacion']['reportable_sut'])->toBeTrue()
        ->and($configuracion['subrogacion']['reportable_siith'])->toBeTrue()
        ->and($configuracion['incremento_remuneracion']['reportable_siith'])->toBeFalse()
        ->and($configuracion['adenda']['reportable_siith'])->toBeFalse();

    // Volver a sembrar no pisa lo que se cambió.
    ConfiguracionReporteMovimiento::where('clase', 'sancion')->update(['reportable_siith' => false]);
    (new ConfiguracionReporteMovimientoSeeder())->run();
    expect(ConfiguracionReporteMovimiento::where('clase', 'sancion')->value('reportable_siith'))->toBeFalse();
});

test('el portal lo decide el instrumento: la acción va al SIITH, la hoja del obrero al SUT, el contrato civil a ninguno', function () {
    $ap = ($this->acto)('ingreso', 'accion_personal', '2026-10-05');
    $obrero = ($this->acto)('ingreso', 'documento_ct', '2026-10-06');
    ($this->acto)('ingreso', 'contrato_civil', '2026-10-07');

    expect(collect(($this->reporte)('siith')['actos'])->pluck('movimiento_id')->all())->toBe([$ap->id])
        ->and(collect(($this->reporte)('sut')['actos'])->pluck('movimiento_id')->all())->toBe([$obrero->id]);
});

test('el ingreso de antes de la columna del instrumento va por su modalidad', function () {
    // Quedó como acción de personal, pero es el de un contrato civil y el de un obrero.
    ($this->acto)('ingreso', 'accion_personal', '2026-10-05', ['tipo_nombramiento_propuesto' => 'servicios_profesionales']);
    $obrero = ($this->acto)('ingreso', 'accion_personal', '2026-10-06', ['tipo_nombramiento_propuesto' => 'codigo_trabajo']);
    $losep = ($this->acto)('ingreso', 'accion_personal', '2026-10-07', ['tipo_nombramiento_propuesto' => 'nombramiento_provisional']);

    expect(collect(($this->reporte)('siith')['actos'])->pluck('movimiento_id')->all())->toBe([$losep->id])
        ->and(collect(($this->reporte)('sut')['actos'])->pluck('movimiento_id')->all())->toBe([$obrero->id]);

    $this->postJson('/api/v1/reportes/siith-sut/transcribir', ['portal' => 'sut', 'movimiento_ids' => [$obrero->id]])->assertOk();
});

test('lo que no se marca como reportable no aparece', function () {
    ConfiguracionReporteMovimiento::where('clase', 'subrogacion')->update(['reportable_siith' => false]);
    ($this->acto)('subrogacion', 'accion_personal', '2026-10-05');
    $cesacion = ($this->acto)('cesacion_funciones', 'accion_personal', '2026-10-06', ['subtipo_movimiento' => 'renuncia']);

    $actos = ($this->reporte)('siith')['actos'];

    expect(collect($actos)->pluck('movimiento_id')->all())->toBe([$cesacion->id])
        ->and($actos[0]['clase'])->toBe('Cesación de Funciones')
        ->and($actos[0]['causal'])->toBe('Renuncia')
        ->and($actos[0]['destino']['puesto'])->toBe('Analista');
});

// ── Lo pendiente y lo transcrito ────────────────────────────────

test('lo que no se transcribió sigue pendiente en los meses siguientes, y lo transcrito sale de los pendientes', function () {
    $septiembre = ($this->acto)('ingreso', 'accion_personal', '2026-09-15');
    $octubre = ($this->acto)('ingreso', 'accion_personal', '2026-10-05');

    expect(collect(($this->reporte)('siith', '2026-10', 'todos')['actos'])->pluck('movimiento_id')->all())->toBe([$octubre->id])
        ->and(collect(($this->reporte)('siith')['actos'])->pluck('movimiento_id')->all())->toBe([$septiembre->id, $octubre->id]);

    $this->postJson('/api/v1/reportes/siith-sut/transcribir', ['portal' => 'siith', 'movimiento_ids' => [$septiembre->id]])
        ->assertOk()
        ->assertJsonPath('datos.transcritos', 1);

    expect(collect(($this->reporte)('siith')['actos'])->pluck('movimiento_id')->all())->toBe([$octubre->id])
        ->and(collect(($this->reporte)('siith', '2026-09', 'transcritos')['actos'])->pluck('movimiento_id')->all())->toBe([$septiembre->id]);

    // Un acto de otro portal no se marca.
    $obrero = ($this->acto)('ingreso', 'documento_ct', '2026-10-06');
    $this->postJson('/api/v1/reportes/siith-sut/transcribir', ['portal' => 'siith', 'movimiento_ids' => [$obrero->id]])->assertStatus(422);
});

test('el acto anulado después de transcribirse vuelve, para anularlo también en el portal', function () {
    $acto = ($this->acto)('ingreso', 'accion_personal', '2026-10-05');
    $this->postJson('/api/v1/reportes/siith-sut/transcribir', ['portal' => 'siith', 'movimiento_ids' => [$acto->id]])->assertOk();

    $acto->forceFill(['estado' => EstadoAccionPersonal::ANULADA])->saveQuietly();

    $fila = ($this->reporte)('siith')['actos'][0];
    expect($fila['movimiento_id'])->toBe($acto->id)
        ->and($fila['anulacion_pendiente'])->toBeTrue();

    $this->postJson('/api/v1/reportes/siith-sut/transcribir', ['portal' => 'siith', 'movimiento_ids' => [$acto->id]])->assertOk();

    expect(($this->reporte)('siith')['actos'])->toBe([])
        ->and(TranscripcionReporte::where('movimiento_id', $acto->id)->value('anulacion_transcrita_en'))->not->toBeNull();
});

// ── Quién ───────────────────────────────────────────────────────

test('el Excel sale con sus columnas, y qué se reporta lo cambia el Director', function () {
    ($this->acto)('ingreso', 'accion_personal', '2026-10-05');

    $this->get('/api/v1/reportes/siith-sut/excel?portal=siith&periodo=2026-10')
        ->assertOk()
        ->assertDownload('reporte_siith_2026-10.xlsx');

    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum');
    $this->getJson('/api/v1/reportes/siith-sut?portal=siith&periodo=2026-10')->assertOk();
    $this->patchJson('/api/v1/reportes/siith-sut/configuracion/sancion', ['reportable_siith' => false])->assertForbidden();

    $this->actingAs($this->user, 'sanctum');
    $this->patchJson('/api/v1/reportes/siith-sut/configuracion/sancion', ['reportable_siith' => false])->assertOk();
    expect(ConfiguracionReporteMovimiento::where('clase', 'sancion')->value('reportable_siith'))->toBeFalse();
});
