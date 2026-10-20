<?php

namespace Tests\Feature\Nomina;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSumario;
use App\Enums\EstadoSubrogacion;
use App\Enums\TipoNombramiento;
use App\Mail\Nomina\NovedadesNominaMail;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
use App\Models\Estructura\Puesto;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\Nomina\EntregaNovedadesNomina;
use App\Models\Nomina\NovedadNomina;
use App\Models\User;
use App\Services\Expediente\SubrogacionService;
use App\Services\Nomina\MesDeNomina;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 7.3a del diseño de Acciones de Personal: las novedades para nómina.
| Financiero paga en su propio sistema (TH, 2026-10-04); el SGTH le entrega
| cada mes lo que pasó, derivado de los actos, con los días en meses de
| treinta. Sin montos desde el 2026-10-20: el SGTH es informativo, y lo que
| fija el acto va en el detalle. Lo que cambia en un mes ya entregado va como
| ajuste en el siguiente.
|
| Hoy es el martes 20 de octubre de 2026.
*/
beforeEach(function () {
    foreach (['admin-uath', 'financiero', 'servidor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }
    permisosDeAccionesPersonal();
    (require database_path('migrations/2026_10_13_000100_crear_permisos_novedades_nomina.php'))->up();
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Obras']);
    $this->puesto = puestoDePrueba($this->unidad, 'Analista', ['plazas' => 20]);

    $this->contador = 0;
    $this->servidor = function (array $contrato = [], ?Puesto $puesto = null): Servidor {
        $this->contador++;
        $p = $puesto ?? $this->puesto;
        $servidor = Servidor::create([
            'cedula' => str_pad((string) (7300000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Nomina'.$this->contador, 'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2018-01-02',
            'puesto_id' => $p->id, 'unidad_administrativa_id' => $p->unidad_administrativa_id,
        ]);
        ContratoServidor::create([
            'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
            'unidad_administrativa_id' => $p->unidad_administrativa_id, 'puesto_id' => $p->id,
            'fecha_inicio' => '2018-01-02', 'estado' => 'vigente', 'remuneracion' => 1000,
            ...$contrato,
        ]);

        return $servidor->fresh();
    };

    /** Un acto ya registrado, como lo deja el trámite. */
    $this->acto = fn (Servidor $s, array $datos) => MovimientoPersonal::create([
        'servidor_id' => $s->id,
        'categoria' => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado' => EstadoAccionPersonal::REGISTRADA,
        'descripcion' => 'Acto de prueba para la nómina.',
        'codigo_registro' => 'AP-2026-'.str_pad((string) (++$this->contador), 4, '0', STR_PAD_LEFT),
        'autorizado_por' => $this->user->id,
        ...$datos,
    ]);

    $this->novedades = fn (string $periodo = '2026-10') => collect(
        $this->getJson("/api/v1/nomina/novedades?periodo={$periodo}")->assertOk()->json('datos.novedades')
    );
});

// ── El mes de treinta días ──────────────────────────────────────

test('el mes es de treinta días: el 31 no cuenta y el fin de febrero vale 30', function () {
    $octubre = MesDeNomina::de('2026-10');
    $febrero = MesDeNomina::de('2027-02');

    expect($octubre->dias(Carbon::parse('2026-10-16'), null))->toBe(15)
        ->and($octubre->dias(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')))->toBe(30)
        ->and($octubre->dias(Carbon::parse('2026-10-31'), Carbon::parse('2026-10-31')))->toBe(1)
        ->and($octubre->dias(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-10')))->toBe(10)
        ->and($febrero->dias(Carbon::parse('2027-02-01'), Carbon::parse('2027-02-28')))->toBe(30)
        ->and($febrero->dias(Carbon::parse('2027-02-15'), null))->toBe(16)
        ->and($octubre->dias(Carbon::parse('2026-11-01'), null))->toBe(0);
});

// ── La entrada y la salida ──────────────────────────────────────

test('quien entra a mitad de mes cuenta desde que entra, con su remuneración, y Servicios Profesionales no va', function () {
    $nuevo = ($this->servidor)(['fecha_inicio' => '2026-10-16', 'remuneracion' => 1200]);
    ($this->servidor)(['fecha_inicio' => '2026-10-01', 'tipo_nombramiento' => TipoNombramiento::SERVICIOS_PROFESIONALES->value, 'fecha_fin' => '2026-12-31']);
    ($this->servidor)();

    $novedades = ($this->novedades)();

    expect($novedades)->toHaveCount(1)
        ->and($novedades[0]['servidor_id'])->toBe($nuevo->id)
        ->and($novedades[0]['concepto'])->toBe('ingreso')
        ->and($novedades[0]['dias'])->toBe(15)
        ->and($novedades[0]['detalle'])->toBe('Ingresa el 16/10/2026: 15 días del mes, con una remuneración de $1.200,00.')
        ->and($novedades[0])->not->toHaveKeys(['base', 'monto']);

    $excel = app(\App\Services\Nomina\NovedadesNominaService::class)->filasDelExcel(MesDeNomina::de('2026-10'))->sole();
    expect(array_keys($excel))->toBe(['MES', 'TIPO', 'CÉDULA', 'SERVIDOR', 'NOVEDAD', 'DETALLE', 'DESDE', 'HASTA', 'DÍAS', 'ACTO']);
});

test('quien sale cuenta hasta el día en que sale', function () {
    $sale = ($this->servidor)(['fecha_fin' => '2026-10-10', 'estado' => 'terminado', 'motivo_fin' => 'Renuncia voluntaria.']);

    $novedad = ($this->novedades)()->sole();

    expect($novedad['servidor_id'])->toBe($sale->id)
        ->and($novedad['concepto'])->toBe('salida')
        ->and($novedad['dias'])->toBe(10)
        ->and($novedad['detalle'])->toBe('Sale el 10/10/2026 (Renuncia voluntaria): 10 días del mes.');
});

test('el ingreso registrado que todavía no rige ya cuenta en su mes', function () {
    $servidor = Servidor::create([
        'cedula' => '7399999999', 'nombre' => 'Por', 'apellido' => 'Ingresar', 'regimen_laboral' => 'losep',
    ]);
    $ingreso = ($this->acto)($servidor, [
        'tipo_movimiento' => 'ingreso', 'fecha_efectiva' => '2026-10-25',
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional', 'remuneracion_propuesta' => 900,
        'puesto_destino_id' => $this->puesto->id, 'unidad_destino_id' => $this->unidad->id,
    ]);

    $novedad = ($this->novedades)()->sole();

    expect($novedad['clave'])->toBe("ingreso:movimiento:{$ingreso->id}")
        ->and($novedad['dias'])->toBe(6)
        ->and($novedad['detalle'])->toContain('con una remuneración de $900,00')
        ->and($novedad['acto'])->toBe($ingreso->codigo_registro);
});

// ── Lo que viene de los actos ───────────────────────────────────

test('la licencia sin remuneración cuenta sus días, y el reintegro la acorta', function () {
    $servidor = ($this->servidor)();
    $licencia = ($this->acto)($servidor, [
        'tipo_movimiento' => 'licencia_sin_remuneracion', 'subtipo_movimiento' => 'asuntos_particulares',
        'fecha_efectiva' => '2026-10-05', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-11-30',
        'remuneracion_origen' => 1000,
    ]);

    $antes = ($this->novedades)()->sole();
    expect($antes['concepto'])->toBe('dias_sin_remuneracion')
        ->and($antes['dias'])->toBe(26);

    ($this->acto)($servidor, ['tipo_movimiento' => 'reintegro', 'fecha_efectiva' => '2026-10-15', 'movimiento_relacionado_id' => $licencia->id]);

    $despues = ($this->novedades)()->sole();
    expect($despues['dias'])->toBe(10)
        ->and($despues['hasta'])->toBe('2026-10-14');
});

test('el cambio de remuneración registrado tarde va en el mes en que rige, y si ya se entregó, como ajuste', function () {
    $servidor = ($this->servidor)(['remuneracion' => 1150]);
    $this->postJson('/api/v1/nomina/novedades/entregar', ['periodo' => '2026-08'])->assertOk();

    ($this->acto)($servidor, [
        'tipo_movimiento' => 'revision_clasificacion', 'fecha_efectiva' => '2026-08-01', 'fecha_registro' => '2026-10-05',
        'remuneracion_origen' => 1000, 'remuneracion_propuesta' => 1150, 'efecto_aplicado_en' => now(),
    ]);

    // Ya no se repite cada mes hasta el del registro: eso era para cobrar la
    // diferencia de cada uno, y el SGTH no la calcula.
    $cambio = fn (string $periodo) => ($this->novedades)($periodo)->where('concepto', 'cambio_remuneracion')->values();
    expect($cambio('2026-09'))->toBeEmpty()
        ->and($cambio('2026-10'))->toBeEmpty();

    $ajuste = collect($this->getJson('/api/v1/nomina/novedades?periodo=2026-10')->assertOk()->json('datos.ajustes'))->sole();
    expect($ajuste['mes_afectado'])->toBe('2026-08')
        ->and($ajuste['concepto'])->toBe('cambio_remuneracion')
        ->and($ajuste['detalle'])->toStartWith('Ajuste de agosto de 2026: ')
        ->and($ajuste['detalle'])->toEndWith(': desde el 01/08/2026 cobra $1.150,00 (antes $1.000,00); se registró el 05/10/2026.')
        ->and($ajuste['dias'])->toBe(30);
});

test('la subrogación dice las remuneraciones que congeló su acción, hasta la víspera de cancelarse', function () {
    $titular = ($this->servidor)();
    $subrogante = ($this->servidor)(['remuneracion' => 800]);
    $accion = ($this->acto)($subrogante, [
        'tipo_movimiento' => 'subrogacion', 'fecha_efectiva' => '2026-10-11', 'fecha_inicio' => '2026-10-11',
        'fecha_fin' => '2026-10-20', 'remuneracion_origen' => 800, 'remuneracion_propuesta' => 1400,
    ]);
    $subrogacion = Subrogacion::create([
        'tipo' => 'subrogacion', 'servidor_subrogante_id' => $subrogante->id, 'servidor_subrogado_id' => $titular->id,
        'unidad_administrativa_id' => $this->unidad->id, 'puesto_subrogado_id' => $this->puesto->id,
        'fecha_inicio' => '2026-10-11', 'fecha_fin' => '2026-10-20', 'motivo' => 'vacaciones',
        'estado' => EstadoSubrogacion::ACTIVA, 'registrado_por' => $this->user->id, 'movimiento_personal_id' => $accion->id,
    ]);

    $novedad = ($this->novedades)()->sole();
    expect($novedad['concepto'])->toBe('subrogacion')
        ->and($novedad['servidor_id'])->toBe($subrogante->id)
        ->and($novedad['dias'])->toBe(10)
        ->and($novedad['detalle'])->toContain('El puesto tiene una remuneración de $1.400,00, y la suya es de $800,00.');

    // Se cancela el 16: surtió efecto del 11 al 15.
    $this->travelTo(Carbon::parse('2026-10-16 09:00'));
    app(SubrogacionService::class)->cancelar($subrogacion->id, 'El titular volvió antes.');

    expect($subrogacion->fresh()->cancelada_en->toDateString())->toBe('2026-10-16');

    $cancelada = ($this->novedades)()->sole();
    expect($cancelada['dias'])->toBe(5);
});

test('la multa va en su mes, con su porcentaje, y la suspensión, sus días en cada mes que toca', function () {
    $servidor = ($this->servidor)();
    $sancion = function (string $tipo, string $desde, array $datos) use ($servidor) {
        $accion = ($this->acto)($servidor, [
            'tipo_movimiento' => 'regimen_disciplinario', 'fecha_efectiva' => $desde, 'remuneracion_origen' => 1000,
        ]);
        $sumario = Sumario::create([
            'servidor_id' => $servidor->id, 'motivo' => 'Sumario de prueba', 'estado' => EstadoSumario::CERRADO,
            'fecha_apertura' => '2026-09-01',
        ]);
        SancionDisciplinaria::create([
            'sumario_id' => $sumario->id, 'movimiento_personal_id' => $accion->id, 'tipo_falta' => 'leve',
            'tipo_sancion' => $tipo, 'fecha_efectiva' => $desde, ...$datos,
        ]);
    };

    $sancion('multa', '2026-10-08', ['porcentaje_multa' => 10]);
    $sancion('suspension', '2026-10-28', ['dias_suspension' => 5, 'tipo_falta' => 'grave']);

    $octubre = ($this->novedades)()->keyBy('concepto');
    expect($octubre['multa']['detalle'])->toBe('Multa del 10 % de la remuneración mensual unificada, que la acción fija en $1.000,00.')
        ->and($octubre['dias_sin_remuneracion']['dias'])->toBe(3);

    $noviembre = ($this->novedades)('2026-11')->sole();
    expect($noviembre['concepto'])->toBe('dias_sin_remuneracion')
        ->and($noviembre['dias'])->toBe(1)
        ->and($noviembre['hasta'])->toBe('2026-11-01');
});

test('la restitución dice las remuneraciones que fija la sentencia', function () {
    $servidor = ($this->servidor)(['fecha_inicio' => '2026-10-19']);
    ($this->acto)($servidor, [
        'tipo_movimiento' => 'restitucion', 'fecha_efectiva' => '2026-10-19', 'remuneraciones_dejadas_de_percibir' => 8450.5,
        'efecto_aplicado_en' => now(),
    ]);

    $pendientes = ($this->novedades)()->firstWhere('concepto', 'remuneraciones_pendientes');

    expect($pendientes['detalle'])->toBe('Remuneraciones dejadas de percibir que fija la sentencia (LOSEP, Art. 46): $8.450,50.');
});

// ── La entrega y los ajustes ────────────────────────────────────

test('la entrega congela el mes y se la envía al jefe de Gestión Financiera, una sola vez', function () {
    $financiera = unidadDePrueba(['nombre' => 'Gestión Financiera']);
    $financiera->forceFill(['es_unidad_financiera' => true])->save();
    $jefe = ($this->servidor)([], puestoDePrueba($financiera, 'Director Financiero', ['es_jefe' => true]));
    User::factory()->create(['servidor_id' => $jefe->id, 'email' => 'jefe.financiero@gadpe.gob.ec']);

    ($this->servidor)(['fecha_inicio' => '2026-10-16']);

    $this->postJson('/api/v1/nomina/novedades/entregar', ['periodo' => '2026-10'])
        ->assertOk()
        ->assertJsonPath('meta.aviso_financiero', 'enviado')
        ->assertJsonPath('datos.entrega.novedades', 1);

    Mail::assertSent(NovedadesNominaMail::class, fn (NovedadesNominaMail $m) => $m->hasTo('jefe.financiero@gadpe.gob.ec')
        && $m->mes === 'octubre de 2026');

    expect(NovedadNomina::count())->toBe(1);

    $this->postJson('/api/v1/nomina/novedades/entregar', ['periodo' => '2026-10'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ya se entregaron'));

    $this->postJson('/api/v1/nomina/novedades/entregar', ['periodo' => '2026-11'])
        ->assertStatus(422);
});

test('lo que cambia en un mes entregado va como ajuste en el siguiente, y una sola vez', function () {
    $servidor = ($this->servidor)();
    $licencia = ($this->acto)($servidor, [
        'tipo_movimiento' => 'licencia_sin_remuneracion', 'subtipo_movimiento' => 'asuntos_particulares',
        'fecha_efectiva' => '2026-10-05', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-30',
        'remuneracion_origen' => 1000,
    ]);
    $entregar = fn (string $periodo) => $this->postJson('/api/v1/nomina/novedades/entregar', ['periodo' => $periodo])->assertOk();
    $ajustes = fn (string $periodo) => collect($this->getJson("/api/v1/nomina/novedades?periodo={$periodo}")->assertOk()->json('datos.ajustes'));

    $entregar('2026-10');

    // Vuelve el 15: diez días sin sueldo en vez de veintiséis.
    ($this->acto)($servidor, ['tipo_movimiento' => 'reintegro', 'fecha_efectiva' => '2026-10-15', 'movimiento_relacionado_id' => $licencia->id]);

    // Lo entregado no se reescribe; lo que cambió se ve aparte.
    $octubre = $this->getJson('/api/v1/nomina/novedades?periodo=2026-10')->assertOk();
    expect($octubre->json('datos.novedades.0.dias'))->toBe(26)
        ->and($octubre->json('datos.cambios_tras_la_entrega.0.dias'))->toBe(10);

    // Sin montos, el ajuste no resta: dice cómo queda.
    $this->travelTo(Carbon::parse('2026-11-20 10:00'));
    $ajuste = $ajustes('2026-11')->sole();
    expect($ajuste['mes_afectado'])->toBe('2026-10')
        ->and($ajuste['dias'])->toBe(10)
        ->and($ajuste['hasta'])->toBe('2026-10-14')
        ->and($ajuste['detalle'])->toStartWith('Ajuste de octubre de 2026: ');

    $entregar('2026-11');

    // Ya se entregó: diciembre no lo repite.
    $this->travelTo(Carbon::parse('2026-12-20 10:00'));
    expect($ajustes('2026-12'))->toBeEmpty();

    // Se anula la licencia: el ajuste dice que ya no corresponde, una vez.
    $licencia->forceFill(['estado' => EstadoAccionPersonal::ANULADA])->saveQuietly();
    $reverso = $ajustes('2026-12')->sole();
    expect($reverso['vigente'])->toBeFalse()
        ->and($reverso['detalle'])->toContain('ya no corresponde');

    $entregar('2026-12');

    $this->travelTo(Carbon::parse('2027-01-20 10:00'));
    expect($ajustes('2027-01'))->toBeEmpty();
});

// ── Quién ───────────────────────────────────────────────────────

test('Financiero las ve y las descarga, pero las entrega Talento Humano', function () {
    $financiero = User::factory()->create();
    $financiero->assignRole('financiero');
    $this->actingAs($financiero, 'sanctum');

    $this->getJson('/api/v1/nomina/novedades?periodo=2026-10')->assertOk();
    $this->get('/api/v1/nomina/novedades/excel?periodo=2026-10')
        ->assertOk()
        ->assertDownload('novedades_nomina_2026-10.xlsx');
    $this->postJson('/api/v1/nomina/novedades/entregar', ['periodo' => '2026-10'])->assertForbidden();

    $servidor = User::factory()->create();
    $servidor->assignRole('servidor');
    $this->actingAs($servidor, 'sanctum');

    $this->getJson('/api/v1/nomina/novedades?periodo=2026-10')->assertForbidden();
    expect(EntregaNovedadesNomina::count())->toBe(0);
});
