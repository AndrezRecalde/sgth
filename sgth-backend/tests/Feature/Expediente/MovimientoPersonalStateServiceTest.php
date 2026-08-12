<?php

namespace Tests\Feature\Expediente;

use App\Enums\CategoriaEventoVinculo;
use App\Enums\EstadoAccionPersonal;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\SubrogacionService;
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

    $this->partidaDisponible = PartidaPresupuestaria::create([
        'codigo' => '510105', 'descripcion' => 'Remuneraciones Unificadas',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $this->partidaNoDisponible = PartidaPresupuestaria::create([
        'codigo' => '510999', 'descripcion' => 'Sin disponibilidad verificada',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => false,
    ]);

    $this->puestoConFondos = Puesto::create([
        'codigo' => 'P-CON-FONDOS',
        'unidad_administrativa_id' => $this->unidad->id,
        'partida_presupuestaria_id' => $this->partidaDisponible->id,
        'plazas' => 5,
    ]);

    $this->puestoSinFondos = Puesto::create([
        'codigo' => 'P-SIN-FONDOS',
        'unidad_administrativa_id' => $this->unidad->id,
        'partida_presupuestaria_id' => $this->partidaNoDisponible->id,
        'plazas' => 5,
    ]);

    $this->servidor = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '1111111111', 'nombre' => 'Titular', 'apellido' => 'Test',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoConFondos->id,
        'unidad_administrativa_id' => $this->unidad->id,
    ]);

    $this->stateService = app(MovimientoPersonalStateService::class);
});

function crearMovimiento(array $overrides = []): MovimientoPersonal
{
    /** @var Servidor $servidor */
    $servidor = $overrides['servidor'] ?? test()->servidor;
    unset($overrides['servidor']);

    // 'comision_servicios': ni modificaVinculo() ni tieneEfectoEconomico()
    // ni creaVinculo() — neutro para los tests genéricos de grafo/registro
    // que no quieren arrastrar la necesidad de un ContratoServidor vigente.
    return MovimientoPersonal::create(array_merge([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => 'comision_servicios',
        'categoria'       => CategoriaEventoVinculo::ACCION_DE_PERSONAL,
        'estado'          => EstadoAccionPersonal::BORRADOR,
        'descripcion'     => 'Movimiento de prueba',
        'fecha_efectiva'  => now()->toDateString(),
        'puesto_destino_id' => $servidor->puesto_id,
        'autorizado_por'  => test()->user->id,
    ], $overrides));
}

// ── Grafo completo de transiciones ──────────────────────────────

dataset('grafo_transiciones', function () {
    // Espeja MovimientoPersonalStateService::TRANSICIONES. Los estados
    // intermedios 'informe_uath' y 'dictamen_presupuestario' se retiraron el
    // 2026-07-29 por no capturar ningún dato.
    $permitidas = [
        'borrador'   => ['suscrita', 'anulada'],
        'suscrita'   => ['registrada', 'anulada'],
        'registrada' => ['notificada'],
        'notificada' => [],
        'anulada'    => [],
    ];
    $estados = array_keys($permitidas);

    $casos = [];
    foreach ($estados as $origen) {
        foreach ($estados as $destino) {
            if ($origen === $destino) {
                continue;
            }
            $permitida = in_array($destino, $permitidas[$origen], true);
            $casos["{$origen} -> {$destino} (" . ($permitida ? 'permitida' : 'bloqueada') . ')']
                = [$origen, $destino, $permitida];
        }
    }
    return $casos;
});

test('el grafo de transiciones se respeta exactamente', function (string $origen, string $destino, bool $permitida) {
    // tipo_movimiento sin efecto económico (traslado) para que este test
    // verifique solo la forma del grafo, sin mezclar el guard de
    // presupuesto (eso se prueba aparte).
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::from($origen)]);

    $destinoEnum = EstadoAccionPersonal::from($destino);

    if ($permitida) {
        $resultado = $this->stateService->transicionar($movimiento, $destinoEnum);
        expect($resultado->estado)->toBe($destinoEnum);
    } else {
        expect(fn () => $this->stateService->transicionar($movimiento, $destinoEnum))
            ->toThrow(ReglaNegocioException::class);
        expect($movimiento->fresh()->estado)->toBe(EstadoAccionPersonal::from($origen));
    }
})->with('grafo_transiciones');

// ── Guard de efecto económico / presupuesto ─────────────────────

test('suscrita exige dictamen_presupuestario_ref si el tipo tiene efecto económico', function () {
    $movimiento = crearMovimiento([
        'tipo_movimiento' => 'incremento_remuneracion',
        'estado' => EstadoAccionPersonal::BORRADOR,
    ]);

    expect(fn () => $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class);

    expect($movimiento->fresh()->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('suscrita falla si la partida del puesto no tiene disponibilidad verificada', function () {
    $servidorSinFondos = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '3333333333', 'nombre' => 'Sin', 'apellido' => 'Fondos',
        'regimen_laboral' => 'losep',
        'puesto_id' => $this->puestoSinFondos->id,
    ]);

    $movimiento = crearMovimiento([
        'servidor' => $servidorSinFondos,
        'tipo_movimiento' => 'incremento_remuneracion',
        'puesto_destino_id' => $this->puestoSinFondos->id,
        'estado' => EstadoAccionPersonal::BORRADOR,
    ]);

    expect(fn () => $this->stateService->transicionar(
        $movimiento,
        EstadoAccionPersonal::SUSCRITA,
        ['dictamen_presupuestario_ref' => 'DICT-2026-001']
    ))->toThrow(ReglaNegocioException::class);

    expect($movimiento->fresh()->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('suscrita procede cuando hay dictamen y la partida está disponible', function () {
    $movimiento = crearMovimiento([
        'tipo_movimiento' => 'incremento_remuneracion',
        'estado' => EstadoAccionPersonal::BORRADOR,
    ]);

    $resultado = $this->stateService->transicionar(
        $movimiento,
        EstadoAccionPersonal::SUSCRITA,
        ['dictamen_presupuestario_ref' => 'DICT-2026-001']
    );

    expect($resultado->estado)->toBe(EstadoAccionPersonal::SUSCRITA);
    expect($resultado->dictamen_presupuestario_ref)->toBe('DICT-2026-001');
});

test('suscrita no exige dictamen para tipos sin efecto económico', function () {
    $movimiento = crearMovimiento(['tipo_movimiento' => 'traslado', 'estado' => EstadoAccionPersonal::BORRADOR]);

    $resultado = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::SUSCRITA);

    expect($resultado->estado)->toBe(EstadoAccionPersonal::SUSCRITA);
});

// ── Registro: codigo_registro + fecha_registro ──────────────────

test('al registrar se asigna codigo_registro con formato AP-año-correlativo y fecha_registro', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::SUSCRITA]);

    $registrado = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::REGISTRADA);

    expect($registrado->codigo_registro)->toMatch('/^AP-' . now()->year . '-\d{4}$/');
    expect($registrado->fecha_registro)->not->toBeNull();
});

test('los correlativos de codigo_registro son consecutivos dentro del mismo año', function () {
    $mov1 = crearMovimiento(['estado' => EstadoAccionPersonal::SUSCRITA]);
    $mov2 = crearMovimiento(['estado' => EstadoAccionPersonal::SUSCRITA]);

    $r1 = $this->stateService->transicionar($mov1, EstadoAccionPersonal::REGISTRADA);
    $r2 = $this->stateService->transicionar($mov2, EstadoAccionPersonal::REGISTRADA);

    expect($r1->codigo_registro)->not->toBe($r2->codigo_registro);
});

// ── Notificación ─────────────────────────────────────────────────

test('notificada exige quién y cuándo', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::REGISTRADA]);

    $notificado = $this->stateService->transicionar($movimiento, EstadoAccionPersonal::NOTIFICADA);

    expect($notificado->notificado_por)->toBe($this->user->id);
    expect($notificado->fecha_notificacion)->not->toBeNull();
});

// ── Inmutabilidad post-registro (guard en el modelo) ─────────────

test('un evento registrado no permite modificar tipo_movimiento por update() directo', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0001']);

    expect(fn () => $movimiento->update(['tipo_movimiento' => 'traslado']))
        ->toThrow(ReglaNegocioException::class);
});

test('un evento registrado no permite modificar fecha_registro ni codigo_registro por update() directo', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::REGISTRADA, 'codigo_registro' => 'AP-2026-0001', 'fecha_registro' => now()]);

    expect(fn () => $movimiento->update(['codigo_registro' => 'AP-2026-9999']))
        ->toThrow(ReglaNegocioException::class);

    expect(fn () => $movimiento->update(['fecha_registro' => now()->addDay()]))
        ->toThrow(ReglaNegocioException::class);
});

test('un evento registrado no permite saltarse el flujo cambiando estado por update() directo', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::REGISTRADA]);

    expect(fn () => $movimiento->update(['estado' => EstadoAccionPersonal::ANULADA]))
        ->toThrow(ReglaNegocioException::class);

    expect(fn () => $movimiento->update(['estado' => EstadoAccionPersonal::BORRADOR]))
        ->toThrow(ReglaNegocioException::class);
});

test('un evento registrado sigue permitiendo editar campos no protegidos', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::REGISTRADA]);

    $movimiento->update(['observacion' => 'Nota administrativa añadida después del registro.']);

    expect($movimiento->fresh()->observacion)->toBe('Nota administrativa añadida después del registro.');
});

test('un evento notificado tampoco permite cambiar de estado por update() directo', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::NOTIFICADA]);

    expect(fn () => $movimiento->update(['estado' => EstadoAccionPersonal::REGISTRADA]))
        ->toThrow(ReglaNegocioException::class);
});

// ── Endpoint de corrección ────────────────────────────────────────

test('no se puede corregir un movimiento que aún no está registrado', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::BORRADOR]);

    expect(fn () => $this->stateService->corregir($movimiento, ['descripcion' => 'Corrección']))
        ->toThrow(ReglaNegocioException::class);
});

test('corregir crea un nuevo movimiento con corrige_a_id y deja el original intacto', function () {
    $original = crearMovimiento([
        'estado' => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro' => 'AP-2026-0001',
        'fecha_registro' => now(),
        'descripcion' => 'Descripción original',
    ]);

    $corregido = $this->stateService->corregir($original, ['descripcion' => 'Descripción corregida']);

    expect($corregido->corrige_a_id)->toBe($original->id);
    expect($corregido->estado)->toBe(EstadoAccionPersonal::BORRADOR);
    expect($corregido->descripcion)->toBe('Descripción corregida');
    expect($corregido->codigo_registro)->toBeNull();
    expect($corregido->servidor_id)->toBe($original->servidor_id);

    $original->refresh();
    expect($original->descripcion)->toBe('Descripción original');
    expect($original->estado)->toBe(EstadoAccionPersonal::REGISTRADA);
});

// ── Quién nace en borrador vs. quién sigue naciendo registrada ───

test('MovimientoPersonalService::registrar crea en borrador los tipos formales de acción de personal', function () {
    $contrato = ContratoServidor::create([
        'servidor_id' => $this->servidor->id,
        'tipo_nombramiento' => 'nombramiento_provisional',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id' => $this->puestoConFondos->id,
        'fecha_inicio' => '2020-01-01',
        'estado' => 'vigente',
    ]);
    $this->servidor->refresh();

    $movimiento = app(MovimientoPersonalService::class)->registrar($this->servidor->id, [
        'tipo_movimiento' => 'prestacion_servicios',
        'descripcion' => 'Prestación de servicios adicionales',
        'fecha_efectiva' => now()->toDateString(),
    ]);

    expect($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
    expect($movimiento->categoria)->toBe(CategoriaEventoVinculo::ACCION_DE_PERSONAL);
});

test('MovimientoPersonalService::registrar sigue creando en registrada los movimientos históricos genéricos', function () {
    // 'traslado' ahora es modificaVinculo() (nace en borrador) — se usa
    // 'cambio_regimen' aquí, que sigue sin serlo, para probar que el
    // resto de tipos genéricos conserva el comportamiento de siempre.
    $movimiento = app(MovimientoPersonalService::class)->registrar($this->servidor->id, [
        'tipo_movimiento' => 'cambio_regimen',
        'descripcion' => 'Cambio de régimen administrativo',
        'fecha_efectiva' => now()->toDateString(),
    ]);

    expect($movimiento->estado)->toBe(EstadoAccionPersonal::REGISTRADA);
});

test('SubrogacionService::registrar crea el movimiento asociado en borrador, no registrada', function () {
    $subrogado = Servidor::create([
        'user_id' => User::factory()->create()->id,
        'cedula' => '4444444444', 'nombre' => 'Titular', 'apellido' => 'Subrogado',
        'regimen_laboral' => 'losep',
    ]);

    // El titular tiene que ocupar de verdad el puesto que se subroga: es lo que
    // distingue una subrogación de un encargo.
    ContratoServidor::create([
        'servidor_id'              => $subrogado->id,
        'tipo_nombramiento'        => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puestoConFondos->id,
        'fecha_inicio'             => '2020-01-01',
        'estado'                   => 'vigente',
    ]);

    app(SubrogacionService::class)->registrar([
        'tipo' => 'subrogacion',
        'servidor_subrogante_id' => $this->servidor->id,
        'servidor_subrogado_id' => $subrogado->id,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_subrogado_id' => $this->puestoConFondos->id,
        'fecha_inicio' => now()->toDateString(),
        'fecha_fin' => now()->addDays(15)->toDateString(),
        'motivo' => 'vacaciones',
    ]);

    $movimiento = MovimientoPersonal::where('servidor_id', $this->servidor->id)
        ->where('tipo_movimiento', 'subrogacion')
        ->first();

    expect($movimiento)->not->toBeNull();
    expect($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
    expect($movimiento->categoria)->toBe(CategoriaEventoVinculo::ACCION_DE_PERSONAL);
});

// ── ReglaNegocioException vía HTTP mapea a 422, no al catch-all ──

test('una transición bloqueada por ReglaNegocioException responde 422 vía HTTP, no 500', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::BORRADOR]);

    // borrador -> registrada no está en el grafo (debe pasar por suscrita).
    $response = $this->putJson(
        "/api/v1/expediente/movimientos/{$movimiento->id}/transicionar",
        ['estado' => 'registrada']
    );

    $response->assertStatus(422);
    expect($response->json('mensaje'))->toContain('No se puede pasar');
    expect($movimiento->fresh()->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

// ── tieneEfectoEconomico() por sí solo también nace en borrador ──

test('MovimientoPersonalService::registrar crea subrogacion en borrador vía tieneEfectoEconomico(), aunque no sea esAccionDePersonal()', function () {
    // subrogacion no es esAccionDePersonal() ni modificaVinculo() ni
    // creaVinculo() — nace en borrador únicamente porque tieneEfectoEconomico()
    // es true. Cubre la combinación que antes solo ASCENSO ejercitaba (el otro
    // tipo con tieneEfectoEconomico()=true, incremento_remuneracion, SÍ es
    // esAccionDePersonal() y ya queda cubierto por el test de "tipos formales").
    // En producción una subrogación se crea vía SubrogacionService::registrar(),
    // no directo aquí — este test verifica el mecanismo genérico de registrar().
    $movimiento = app(MovimientoPersonalService::class)->registrar($this->servidor->id, [
        'tipo_movimiento' => 'subrogacion',
        'descripcion' => 'Subrogación de puesto',
        'fecha_efectiva' => now()->toDateString(),
        'puesto_destino_id' => $this->puestoConFondos->id,
    ]);

    expect($movimiento->estado)->toBe(EstadoAccionPersonal::BORRADOR);
    expect($movimiento->categoria)->toBe(CategoriaEventoVinculo::ACCION_DE_PERSONAL);
});

// ── Guard de estado en la generación del PDF de Acción de Personal ──

test('el PDF de Acción de Personal no se genera si el movimiento no está registrado ni notificado', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::BORRADOR]);

    expect(fn () => app(\App\Services\Expediente\AccionPersonalPdfService::class)->generarContent($movimiento->id))
        ->toThrow(ReglaNegocioException::class);
});

test('el PDF de Acción de Personal se genera cuando el movimiento está registrado', function () {
    $movimiento = crearMovimiento([
        'estado' => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro' => 'AP-2026-0001',
        'fecha_registro' => now(),
    ]);

    $resultado = app(\App\Services\Expediente\AccionPersonalPdfService::class)->generarContent($movimiento->id);

    expect($resultado)->toHaveKeys(['content', 'filename']);
});

test('el endpoint HTTP de descarga del PDF responde 422 (no 500) para un movimiento no registrado', function () {
    $movimiento = crearMovimiento(['estado' => EstadoAccionPersonal::BORRADOR]);

    $response = $this->getJson("/api/v1/expediente/movimientos/{$movimiento->id}/accion-personal-pdf");

    $response->assertStatus(422);
});
