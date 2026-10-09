<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-01', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puestoA = Puesto::create([
        'codigo' => 'P-A', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->puestoB = Puesto::create([
        'codigo' => 'P-B', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->stateService = app(MovimientoPersonalStateService::class);
});

// ── Exclusión mutua ────────────────────────────────────────────

test('ningún tipo_movimiento es creaVinculo() y modificaVinculo() a la vez', function () {
    foreach (TipoMovimientoPersonal::cases() as $tipo) {
        expect($tipo->creaVinculo() && $tipo->modificaVinculo())
            ->toBeFalse("El tipo '{$tipo->value}' es creaVinculo() y modificaVinculo() a la vez.");
    }
});

// ── Completitud de datos propuestos — INGRESO (creaVinculo) ──────

test('un ingreso sin tipo_nombramiento_propuesto no puede registrarse', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1111111111', 'nombre' => 'Nuevo', 'apellido' => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'       => $servidor->id,
        'tipo_movimiento'   => 'ingreso',
        'categoria'         => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'            => EstadoAccionPersonal::SUSCRITA,
        'descripcion'       => 'Ingreso propuesto sin tipo de nombramiento',
        'fecha_efectiva'    => '2026-08-01',
        'puesto_destino_id' => $this->puestoA->id,
        'unidad_destino_id' => $this->unidad->id,
        'autorizado_por'    => $this->user->id,
    ]);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);

    expect(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(0);
});

test('un ingreso sin puesto o unidad propuestos no puede registrarse', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '2222222222', 'nombre' => 'Nuevo', 'apellido' => 'SinPuesto',
        'regimen_laboral' => 'losep',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'                 => $servidor->id,
        'tipo_movimiento'             => 'ingreso',
        'categoria'                   => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'                      => EstadoAccionPersonal::SUSCRITA,
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'descripcion'                 => 'Ingreso propuesto sin puesto',
        'fecha_efectiva'              => '2026-08-01',
        'autorizado_por'              => $this->user->id,
    ]);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);
});

// ── Completitud de datos propuestos — modificaVinculo() ──────────

/*
| Estas dos usaban el tipo plano 'traslado' y pasaron a 'traspaso' el 2026-09-28.
| Lo que comprueban —que reubicar exige puesto de destino, y que reubicar
| conserva el mismo contrato— sigue siendo cierto, pero del traspaso: TH aclaró
| que el traslado es el intercambio de personal ENTRE INSTITUCIONES y no toca el
| vínculo, así que afirmarlo del traslado era fijar el malentendido que tenía el
| código. Que un traslado se registre sin puesto y deje el vínculo quieto lo
| cubre ahora AccionPersonalTaxonomiaTest.
*/
test('un traspaso sin puesto_destino_id no puede registrarse', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '3333333333', 'nombre' => 'Titular', 'apellido' => 'Test',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2020-01-01',
        'estado' => 'vigente',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => 'traspaso',
        'categoria'       => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'          => EstadoAccionPersonal::SUSCRITA,
        'descripcion'     => 'Traspaso sin puesto de destino',
        'fecha_efectiva'  => '2026-08-01',
        'autorizado_por'  => $this->user->id,
    ]);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);
});

// ── End-to-end: INGRESO ──────────────────────────────────────────

test('un ingreso completo, al registrarse, crea el ContratoServidor y sincroniza Servidor', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '4444444444', 'nombre' => 'Completo', 'apellido' => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    expect($servidor->contratoVigente)->toBeNull();

    $movimiento = MovimientoPersonal::create([
        'servidor_id'                 => $servidor->id,
        'tipo_movimiento'             => 'ingreso',
        'categoria'                   => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'                      => EstadoAccionPersonal::BORRADOR,
        'descripcion'                 => 'Ingreso incompleto inicialmente',
        'fecha_efectiva'              => '2026-08-01',
        'autorizado_por'              => $this->user->id,
    ]);

    // Incompleto: falla y no crea nada.
    $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA))
        ->toThrow(ReglaNegocioException::class);
    expect(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(0);

    // Se completan los datos propuestos (sigue en 'suscrita', editable
    // porque el guard de inmutabilidad solo bloquea desde REGISTRADA/NOTIFICADA).
    $movimiento->update([
        'tipo_nombramiento_propuesto' => 'nombramiento_provisional',
        'puesto_destino_id'           => $this->puestoA->id,
        'unidad_destino_id'           => $this->unidad->id,
        'remuneracion_propuesta'      => 850.00,
        'numero_contrato'             => 'CT-2026-0001',
    ]);

    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA);

    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->first();
    expect($contrato)->not->toBeNull();
    expect($contrato->estado->value)->toBe('vigente');
    expect($contrato->puesto_id)->toBe($this->puestoA->id);
    expect($contrato->tipo_nombramiento->value)->toBe('nombramiento_provisional');

    $servidor->refresh();
    expect($servidor->puesto_id)
        ->toBe($contrato->puesto_id)
        ->toBe($this->puestoA->id);
    expect($servidor->unidad_administrativa_id)->toBe($contrato->unidad_administrativa_id);
});

// ── End-to-end: traslado (reubica dentro del mismo vínculo) ──────

test('un traspaso reubica al servidor conservando el mismo contrato', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '5555555555', 'nombre' => 'Titular', 'apellido' => 'Traslado',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $contratoOriginal = ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2020-0001',
        'resolucion_numero' => 'RES-2020-0007',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2020-01-01',
        'estado' => 'vigente',
    ]);

    $movimiento = MovimientoPersonal::create([
        'servidor_id'       => $servidor->id,
        'tipo_movimiento'   => 'traspaso',
        'categoria'         => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'            => EstadoAccionPersonal::SUSCRITA,
        'descripcion'       => 'Traspaso al puesto B',
        'fecha_efectiva'    => '2026-08-01',
        'puesto_destino_id' => $this->puestoB->id,
        'autorizado_por'    => $this->user->id,
    ]);

    $registrado = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA);

    // El traspaso no interrumpe la relación laboral: sigue habiendo UN solo
    // contrato, el mismo, con su número y resolución originales — no existe
    // ningún documento nuevo que justificara crear otro.
    expect(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1);

    $contratoOriginal->refresh();
    expect($contratoOriginal->estado->value)->toBe('vigente')
        ->and($contratoOriginal->numero_contrato)->toBe('CT-2020-0001')
        ->and($contratoOriginal->resolucion_numero)->toBe('RES-2020-0007')
        ->and($contratoOriginal->fecha_inicio->toDateString())->toBe('2020-01-01')
        ->and($contratoOriginal->puesto_id)->toBe($this->puestoB->id)
        ->and($contratoOriginal->tipo_nombramiento->value)->toBe('nombramiento_permanente');

    // Integridad en una sola cadena: Servidor === contrato vigente === puesto B.
    $servidor->refresh();
    expect($servidor->puesto_id)
        ->toBe($servidor->contratoVigente->puesto_id)
        ->toBe($this->puestoB->id);

    // Sin constancia duplicada: solo existe el traspaso mismo, y el contrato
    // no se anota en la bitácora como nacido sin acción, porque la tiene.
    $movimientosDelServidor = MovimientoPersonal::where('servidor_id', $servidor->id)->get();
    expect($movimientosDelServidor)->toHaveCount(1);
    expect($movimientosDelServidor->first()->tipo_movimiento->value)->toBe('traspaso');
    expect(\App\Models\Expediente\EventoVinculo::where('servidor_id', $servidor->id)->exists())->toBeFalse();
});

// ── End-to-end: prestación de servicios (reubica sin subtipo) ────

/*
| La prestación de servicios es la misma figura que el traspaso repartida por
| tipo de nombramiento: TH (2026-09-28) la describió como «prácticamente como un
| traspaso pero se hace para servidores con nombramientos Provisionales,
| Ocasionales, Servicios Profesionales, igualmente con su situación actual y
| situación propuesta», y el traspaso solo aplica a permanentes.
|
| Hasta ese día no reubicaba a nadie: reubicar se decidía solo con el subtipo, y
| este tipo no tiene. Se guardaba la acción, se registraba, y el contrato seguía
| apuntando al puesto de siempre.
*/
test('una prestación de servicios sin puesto_destino_id no puede registrarse', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1212121212', 'nombre' => 'Provisional', 'apellido' => 'SinPuesto',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_provisional',
        'numero_contrato' => 'CT-2022-0001',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2022-01-01',
        'estado' => 'vigente',
    ]);
    $servidor->refresh();

    $movimiento = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento' => TipoMovimientoPersonal::PRESTACION_SERVICIOS->value,
            'descripcion'     => 'Prestación de servicios sin puesto propuesto',
            'fecha_efectiva'  => '2026-08-01',
        ]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    // El mensaje nombra el tipo porque no hay subtipo del que sacar la
    // etiqueta: decía el nombre de otra cosa cuando caía al subtipo.
    expect(fn () => $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA))
        ->toThrow(
            ReglaNegocioException::class,
            "No se puede registrar 'Prestación de Servicios' sin especificar el puesto propuesto."
        );
});

test('una prestación de servicios reubica al servidor conservando el mismo contrato', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1313131313', 'nombre' => 'Provisional', 'apellido' => 'Reubicado',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $contratoOriginal = ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_provisional',
        'numero_contrato' => 'CT-2022-0002',
        'resolucion_numero' => 'RES-2022-0031',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2022-01-01',
        'remuneracion' => 986.00,
        'estado' => 'vigente',
    ]);
    $servidor->refresh();

    $movimiento = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'   => TipoMovimientoPersonal::PRESTACION_SERVICIOS->value,
            'descripcion'       => 'Prestación de servicios en el puesto B',
            'fecha_efectiva'    => '2026-08-01',
            'puesto_destino_id' => $this->puestoB->id,
            'unidad_destino_id' => $this->unidad->id,
        ]);

    // La situación actual se congela al crear la acción, igual que en el
    // traspaso: es la columna izquierda del documento impreso.
    expect($movimiento->puesto_origen_id)->toBe($this->puestoA->id)
        ->and($movimiento->unidad_origen_id)->toBe($this->unidad->id);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $registrado = $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($registrado->estado)->toBe(EstadoAccionPersonal::REGISTRADA);

    // Un solo contrato, el mismo, con su número, su resolución y su fecha de
    // inicio: no se firma ningún instrumento nuevo, así que no nace otro.
    expect(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1);

    $contratoOriginal->refresh();
    expect($contratoOriginal->estado->value)->toBe('vigente')
        ->and($contratoOriginal->numero_contrato)->toBe('CT-2022-0002')
        ->and($contratoOriginal->resolucion_numero)->toBe('RES-2022-0031')
        ->and($contratoOriginal->fecha_inicio->toDateString())->toBe('2022-01-01')
        ->and($contratoOriginal->puesto_id)->toBe($this->puestoB->id)
        ->and($contratoOriginal->tipo_nombramiento->value)->toBe('nombramiento_provisional')
        // Mismo grupo ocupacional, misma remuneración — supuesto tomado del
        // traspaso y documentado en reestructurarDesdeMovimiento().
        ->and((float) $contratoOriginal->remuneracion)->toBe(986.00);

    $servidor->refresh();
    expect($servidor->puesto_id)
        ->toBe($servidor->contratoVigente->puesto_id)
        ->toBe($this->puestoB->id);
});

test('una prestación de servicios de Servicios Profesionales no compite por la plaza', function () {
    $puestoUnico = Puesto::create([
        'codigo' => 'P-UNICO', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1,
    ]);

    // La única plaza ya está tomada por un permanente.
    $titular = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1414141414', 'nombre' => 'Titular', 'apellido' => 'DeLaPlaza',
        'regimen_laboral' => 'losep',
        'puesto_id' => $puestoUnico->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    ContratoServidor::create([
        'servidor_id' => $titular->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2019-0050',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $puestoUnico->id,
        'fecha_inicio' => '2019-01-01',
        'estado' => 'vigente',
    ]);

    $profesional = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1515151515', 'nombre' => 'Servicios', 'apellido' => 'Profesionales',
        'regimen_laboral' => 'servicios_profesionales',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $contrato = ContratoServidor::create([
        'servidor_id' => $profesional->id,
        'tipo_nombramiento' => 'servicios_profesionales',
        'numero_contrato' => 'CT-2025-0300',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2025-01-01',
        'fecha_fin' => '2026-12-31',
        'estado' => 'vigente',
    ]);
    $profesional->refresh();

    $movimiento = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($profesional->id, [
            'tipo_movimiento'   => TipoMovimientoPersonal::PRESTACION_SERVICIOS->value,
            'descripcion'       => 'Prestación de servicios al puesto único',
            'fecha_efectiva'    => '2026-08-01',
            'puesto_destino_id' => $puestoUnico->id,
            'unidad_destino_id' => $this->unidad->id,
        ]);

    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    // Servicios Profesionales no ocupa plaza (TipoNombramiento::ocupaPlaza()),
    // así que la plaza única del permanente no le estorba: es justamente el
    // caso que la prestación de servicios tiene que poder resolver.
    $contrato->refresh();
    expect($contrato->puesto_id)->toBe($puestoUnico->id)
        ->and($contrato->estado->value)->toBe('vigente');
});

// ── PUT /expediente/servidores/{id} rechaza puesto/unidad/tipo_nombramiento ──

test('PUT servidores/{id} con puesto_id en el body responde 422', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '6666666666', 'nombre' => 'Titular', 'apellido' => 'Prohibited',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $response = $this->putJson("/api/v1/expediente/servidores/{$servidor->id}", [
        'puesto_id' => $this->puestoB->id,
    ]);

    $response->assertStatus(422)->assertJsonStructure(['errores' => ['puesto_id']]);
    expect($servidor->fresh()->puesto_id)->toBe($this->puestoA->id);
});

test('PUT servidores/{id} con unidad_administrativa_id en el body responde 422', function () {
    $otraUnidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-02', 'nombre' => 'Otra Unidad', 'nivel' => 1,
    ]);

    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '7777777777', 'nombre' => 'Titular', 'apellido' => 'Prohibited',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $response = $this->putJson("/api/v1/expediente/servidores/{$servidor->id}", [
        'unidad_administrativa_id' => $otraUnidad->id,
    ]);

    $response->assertStatus(422)->assertJsonStructure(['errores' => ['unidad_administrativa_id']]);
    expect($servidor->fresh()->unidad_administrativa_id)->toBe($this->unidad->id);
});

test('PUT servidores/{id} con tipo_nombramiento en el body responde 422', function () {
    $servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '8888888888', 'nombre' => 'Titular', 'apellido' => 'Prohibited',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'tipo_nombramiento' => 'nombramiento_provisional',
    ]);

    $response = $this->putJson("/api/v1/expediente/servidores/{$servidor->id}", [
        'tipo_nombramiento' => 'nombramiento_permanente',
    ]);

    $response->assertStatus(422)->assertJsonStructure(['errores' => ['tipo_nombramiento']]);
    expect($servidor->fresh()->tipo_nombramiento->value)->toBe('nombramiento_provisional');
});

// ── Comisión de servicios: ausencia temporal, no reubicación ─────

test('una comisión de servicios no mueve al servidor de puesto', function () {
    $servidor = Servidor::create([
        'cedula' => '6666666666', 'nombre' => 'En', 'apellido' => 'Comision',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    $contrato = ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2018-0009',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2018-01-01',
        'estado' => 'vigente',
    ]);

    $movimiento = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
            'descripcion'        => 'Comisión de servicios en otra institución',
            'fecha_efectiva'     => '2026-01-01',
            'fecha_inicio'       => '2026-01-01',
            'fecha_fin'          => '2028-01-01',
        ]);

    // Comparte el tipo paraguas con el traspaso, pero no exige puesto destino
    // ni reubica: el servidor conserva su puesto y vuelve al terminar.
    $movimiento = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($movimiento->fresh(), EstadoAccionPersonal::REGISTRADA);

    $contrato->refresh();

    expect($contrato->estado->value)->toBe('vigente')
        ->and($contrato->puesto_id)->toBe($this->puestoA->id)
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->count())->toBe(1);
});

// ── Actividad laboral: acciones anidadas bajo su vínculo ─────────

test('la actividad laboral agrupa las acciones bajo el contrato al que pertenecen', function () {
    $servidor = Servidor::create([
        'cedula' => '7777777777', 'nombre' => 'Con', 'apellido' => 'Historial',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2018-0100',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2018-01-01',
        'estado' => 'vigente',
    ]);

    $servicio = app(\App\Services\Expediente\MovimientoPersonalService::class);

    $traspaso = $servicio->registrar($servidor->id, [
        'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso',
        'fecha_efectiva'     => '2026-03-15',
        'puesto_destino_id'  => $this->puestoB->id,
        'unidad_destino_id'  => $this->unidad->id,
    ]);
    $traspaso = $this->stateService->transicionar($traspaso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($traspaso->fresh(), EstadoAccionPersonal::REGISTRADA);

    $comision = $servicio->registrar($servidor->id, [
        'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
        'descripcion'        => 'Comisión',
        'fecha_efectiva'     => now()->subMonth()->toDateString(),
        'fecha_inicio'       => now()->subMonth()->toDateString(),
        'fecha_fin'          => now()->addYear()->toDateString(),
    ]);
    $comision = $this->stateService->transicionar($comision, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($comision->fresh(), EstadoAccionPersonal::REGISTRADA);

    $actividad = app(\App\Services\Expediente\ContratoServidorService::class)
        ->actividadLaboral($servidor->id);

    expect($actividad)->toHaveCount(1);

    $vinculo = $actividad[0];

    // Un solo contrato, con sus dos acciones colgando y la situación derivada
    // de la comisión vigente hoy.
    expect($vinculo['contrato']->numero_contrato)->toBe('CT-2018-0100')
        ->and($vinculo['acciones'])->toHaveCount(2)
        // El nombre legal desde la fase 1.1: el «Traspaso» de un permanente es
        // el traslado del Art. 35 de la LOSEP.
        ->and($vinculo['acciones'][0]['etiqueta'])->toBe('Traslado')
        ->and($vinculo['situacion'])->not->toBeNull()
        ->and($vinculo['situacion']['etiqueta'])->toBe('Comisión de Servicios sin Remuneración');
});

// ── Situación actual congelada al crear la acción ────────────────

test('la acción congela dónde estaba el servidor al momento de registrarla', function () {
    $servidor = Servidor::create([
        'cedula' => '8888888888', 'nombre' => 'Origen', 'apellido' => 'Capturado',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoA->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'fecha_ingreso_institucion' => '2018-01-01',
    ]);

    ContratoServidor::create([
        'servidor_id' => $servidor->id,
        'tipo_nombramiento' => 'nombramiento_permanente',
        'numero_contrato' => 'CT-2018-0200',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoA->id,
        'fecha_inicio' => '2018-01-01',
        'estado' => 'vigente',
    ]);

    $traspaso = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'    => \App\Enums\TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => \App\Enums\SubtipoMovimientoPersonal::TRASPASO->value,
            'descripcion'        => 'Traspaso al puesto B',
            'fecha_efectiva'     => '2026-05-01',
            'puesto_destino_id'  => $this->puestoB->id,
            'unidad_destino_id'  => $this->unidad->id,
        ]);

    // Es la columna "situación actual" del PDF y, desde que el traspaso
    // actualiza el contrato en vez de duplicarlo, el único registro de dónde
    // venía la persona.
    expect($traspaso->puesto_origen_id)->toBe($this->puestoA->id)
        ->and($traspaso->unidad_origen_id)->toBe($this->unidad->id);

    $traspaso = $this->stateService->transicionar($traspaso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($traspaso->fresh(), EstadoAccionPersonal::REGISTRADA);

    // El contrato ya apunta al puesto B, pero la acción conserva el A.
    expect($servidor->fresh()->contratoVigente->puesto_id)->toBe($this->puestoB->id)
        ->and($traspaso->fresh()->puesto_origen_id)->toBe($this->puestoA->id);
});

test('un ingreso no tiene situación actual que congelar', function () {
    $servidor = Servidor::create([
        'cedula' => '9999999999', 'nombre' => 'Primer', 'apellido' => 'Ingreso',
        'regimen_laboral' => 'losep',
    ]);

    $ingreso = app(\App\Services\Expediente\MovimientoPersonalService::class)
        ->registrar($servidor->id, [
            'tipo_movimiento'             => 'ingreso',
            'tipo_nombramiento_propuesto' => 'nombramiento_permanente',
            'puesto_destino_id'           => $this->puestoA->id,
            'unidad_destino_id'           => $this->unidad->id,
            'descripcion'                 => 'Primer ingreso',
            'fecha_efectiva'              => '2026-08-01',
        ]);

    expect($ingreso->puesto_origen_id)->toBeNull()
        ->and($ingreso->unidad_origen_id)->toBeNull();
});
