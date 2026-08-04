<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\AusenciaTemporalService;
use App\Services\Expediente\MovimientoPersonalService;
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
        'codigo' => 'UATH-AUS', 'nombre' => 'Dirección de Obras Públicas', 'nivel' => 1,
    ]);

    // Una sola plaza: es la condición que hace interesante el reemplazo.
    $this->puesto = Puesto::create([
        'codigo' => 'P-AUS', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1,
    ]);

    $this->service      = app(MovimientoPersonalService::class);
    $this->stateService = app(MovimientoPersonalStateService::class);
    $this->ausencias    = app(AusenciaTemporalService::class);

    $this->contador = 0;

    $this->servidor = function (?TipoNombramiento $nombramiento = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'user_id'                   => User::factory()->create()->id,
            'cedula'                    => str_pad((string) (7000000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Titular',
            'apellido'                  => 'Ausencia'.$this->contador,
            'regimen_laboral'           => 'losep',
            'puesto_id'                 => $this->puesto->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        if ($nombramiento) {
            ContratoServidor::create([
                'servidor_id'              => $servidor->id,
                'tipo_nombramiento'        => $nombramiento->value,
                'numero_contrato'          => 'CT-BASE-'.$this->contador,
                'unidad_administrativa_id' => $this->unidad->id,
                'puesto_id'                => $this->puesto->id,
                'fecha_inicio'             => '2018-01-01',
                'estado'                   => 'vigente',
            ]);
        }

        return $servidor->fresh('contratoVigente');
    };

    /** Comisión de servicios registrada: la ausencia que deja el hueco. */
    $this->comisionRegistrada = function (array $extra = []): array {
        $titular = ($this->servidor)(TipoNombramiento::PERMANENTE);

        $comision = $this->service->registrar($titular->id, [
            'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
            'descripcion'        => 'Comisión de servicios en el Ministerio',
            'fecha_efectiva'     => '2026-08-01',
            'fecha_inicio'       => '2026-08-01',
            'fecha_fin'          => '2028-07-31',
            ...$extra,
        ]);

        $comision = $this->stateService->transicionar($comision, EstadoAccionPersonal::SUSCRITA);
        $comision = $this->stateService->transicionar($comision->fresh(), EstadoAccionPersonal::REGISTRADA);

        return [$titular, $comision->fresh()];
    };

    /** Datos base de un ingreso de reemplazo sobre la misma plaza. */
    $this->datosReemplazo = fn (MovimientoPersonal $ausencia, array $extra = []): array => [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Ingreso para cubrir comisión de servicios',
        'fecha_efectiva'              => '2026-08-01',
        'cubre_movimiento_id'         => $ausencia->id,
        ...$extra,
    ];
});

// ── Detección de la ausencia ────────────────────────────────────

test('una comisión registrada aparece como ausencia vigente durante su período', function () {
    [$titular] = ($this->comisionRegistrada)();

    $filas = $this->ausencias->listar(['fecha' => '2027-01-15']);

    expect($filas)->toHaveCount(1)
        ->and($filas[0]['servidor']['id'])->toBe($titular->id)
        ->and($filas[0]['etiqueta'])->toBe('Comisión de Servicios con Remuneración')
        ->and($filas[0]['desde'])->toBe('2026-08-01')
        ->and($filas[0]['hasta'])->toBe('2028-07-31')
        ->and($filas[0]['reemplazo'])->toBeNull();
});

test('la ausencia desaparece del listado al vencer el período, sin tarea que la apague', function () {
    ($this->comisionRegistrada)();

    expect($this->ausencias->listar(['fecha' => '2028-08-01']))->toBeEmpty()
        ->and($this->ausencias->listar(['fecha' => '2026-07-31']))->toBeEmpty();
});

test('una comisión que sigue en borrador todavía no cuenta como ausencia', function () {
    $titular = ($this->servidor)(TipoNombramiento::PERMANENTE);

    $this->service->registrar($titular->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'descripcion'        => 'Comisión sin aprobar',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'fecha_fin'          => '2028-07-31',
    ]);

    expect($this->ausencias->listar(['fecha' => '2027-01-15']))->toBeEmpty();
});

test('un traspaso no es ausencia temporal: el servidor sí está, solo cambió de sitio', function () {
    $titular = ($this->servidor)(TipoNombramiento::PERMANENTE);

    $otra = UnidadAdministrativa::create([
        'codigo' => 'UATH-B', 'nombre' => 'Dirección Financiera', 'nivel' => 1,
    ]);
    $destino = Puesto::create([
        'codigo' => 'P-DEST', 'unidad_administrativa_id' => $otra->id, 'plazas' => 3,
    ]);

    $traspaso = $this->service->registrar($titular->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::TRASPASO->value,
        'descripcion'        => 'Traspaso a Financiera',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'unidad_destino_id'  => $otra->id,
        'puesto_destino_id'  => $destino->id,
    ]);

    $traspaso = $this->stateService->transicionar($traspaso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($traspaso->fresh(), EstadoAccionPersonal::REGISTRADA);

    expect($this->ausencias->listar(['fecha' => '2027-01-15']))->toBeEmpty();
});

// ── El reemplazo y la plaza ─────────────────────────────────────

test('el reemplazo entra al puesto aunque la única plaza siga ocupada por el titular', function () {
    [$titular, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();

    $ingreso = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));
    $ingreso = $this->stateService->transicionar($ingreso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($ingreso->fresh(), EstadoAccionPersonal::REGISTRADA, [
        'numero_contrato'        => 'CT-2026-SUPL',
        'remuneracion_propuesta' => 900,
        'fecha_fin_propuesta'    => '2028-07-31',
    ]);

    $contrato = ContratoServidor::where('servidor_id', $suplente->id)->firstOrFail();

    expect($contrato->cubre_movimiento_id)->toBe($comision->id)
        ->and($contrato->esReemplazo())->toBeTrue()
        // El titular conserva su vínculo: la comisión no lo cierra.
        ->and($titular->fresh()->contratoVigente)->not->toBeNull();
});

test('sin el enlace de reemplazo el mismo ingreso choca contra la plaza ocupada', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();

    expect(fn () => $this->service->registrar(
        $suplente->id,
        [...($this->datosReemplazo)($comision), 'cubre_movimiento_id' => null]
    ))->not->toThrow(ReglaNegocioException::class);

    $ingreso = MovimientoPersonal::where('servidor_id', $suplente->id)->firstOrFail();
    $ingreso = $this->stateService->transicionar($ingreso, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->stateService->transicionar(
        $ingreso->fresh(),
        EstadoAccionPersonal::REGISTRADA,
        ['numero_contrato' => 'CT-2026-CHOQUE', 'remuneracion_propuesta' => 900]
    ))->toThrow(ReglaNegocioException::class, 'no tiene plazas disponibles');
});

test('el reemplazo tampoco consume plaza para un tercer ingreso ordinario', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $ingreso  = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));
    $ingreso  = $this->stateService->transicionar($ingreso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($ingreso->fresh(), EstadoAccionPersonal::REGISTRADA, [
        'numero_contrato'        => 'CT-2026-SUPL2',
        'remuneracion_propuesta' => 900,
    ]);

    // La plaza sigue siendo del titular: un ingreso ordinario debe seguir
    // rebotando, ni más ni menos que antes de existir el reemplazo.
    $tercero = ($this->servidor)();
    $otro    = $this->service->registrar($tercero->id, [
        ...($this->datosReemplazo)($comision),
        'cubre_movimiento_id'         => null,
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
    ]);
    $otro = $this->stateService->transicionar($otro, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->stateService->transicionar(
        $otro->fresh(),
        EstadoAccionPersonal::REGISTRADA,
        ['numero_contrato' => 'CT-2026-TERC', 'remuneracion_propuesta' => 1200]
    ))->toThrow(ReglaNegocioException::class, 'no tiene plazas disponibles');
});

// ── Reglas del enlace ───────────────────────────────────────────

test('solo un ingreso puede declararse reemplazo', function () {
    [, $comision] = ($this->comisionRegistrada)();
    $otro = ($this->servidor)(TipoNombramiento::PERMANENTE);

    expect(fn () => $this->service->registrar($otro->id, [
        'tipo_movimiento'     => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento'  => SubtipoMovimientoPersonal::TRASLADO_ADMINISTRATIVO->value,
        'descripcion'         => 'Traslado que pretende cubrir',
        'fecha_efectiva'      => '2026-09-01',
        'cubre_movimiento_id' => $comision->id,
    ]))->toThrow(ReglaNegocioException::class, 'Solo un Ingreso y Vinculación');
});

test('no se puede cubrir algo que no es una ausencia temporal', function () {
    $titular = ($this->servidor)(TipoNombramiento::PERMANENTE);

    $renuncia = $this->service->registrar($titular->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia voluntaria',
        'fecha_efectiva'     => '2026-08-01',
    ]);

    $suplente = ($this->servidor)();

    expect(fn () => $this->service->registrar(
        $suplente->id,
        ($this->datosReemplazo)($renuncia)
    ))->toThrow(ReglaNegocioException::class, 'no es una comisión de servicios');
});

test('la ausencia debe estar registrada antes de contratar quien la cubra', function () {
    $titular = ($this->servidor)(TipoNombramiento::PERMANENTE);

    $borrador = $this->service->registrar($titular->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::COMISION_SIN_REMUNERACION->value,
        'descripcion'        => 'Comisión aún en borrador',
        'fecha_efectiva'     => '2026-08-01',
        'fecha_inicio'       => '2026-08-01',
        'fecha_fin'          => '2028-07-31',
    ]);

    $suplente = ($this->servidor)();

    expect(fn () => $this->service->registrar(
        $suplente->id,
        ($this->datosReemplazo)($borrador)
    ))->toThrow(ReglaNegocioException::class, 'debe estar registrada');
});

test('una ausencia temporal no se cubre con nombramiento permanente', function () {
    [, $comision] = ($this->comisionRegistrada)();
    $suplente = ($this->servidor)();

    expect(fn () => $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision, [
        'tipo_nombramiento_propuesto' => TipoNombramiento::PERMANENTE->value,
    ])))->toThrow(ReglaNegocioException::class, 'Servicios Ocasionales o Servicios Profesionales');
});

test('el reemplazo no puede extenderse más allá de la ausencia', function () {
    [, $comision] = ($this->comisionRegistrada)();
    $suplente = ($this->servidor)();

    expect(fn () => $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision, [
        'fecha_fin_propuesta' => '2029-01-31',
    ])))->toThrow(ReglaNegocioException::class, 'no puede extenderse más allá del 2028-07-31');
});

/**
 * La validación al crear no basta: el borrador es editable, así que el plazo
 * puede alargarse después. El contrato nace al registrar, y es ahí donde el
 * límite tiene que volver a comprobarse.
 */
test('alargar el plazo del reemplazo en el borrador no burla el límite de la ausencia', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $ingreso  = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));

    // Se edita el borrador con una fecha que excede el regreso del titular.
    $this->service->actualizarBorrador($ingreso, ['fecha_fin_propuesta' => '2029-06-30']);

    $ingreso = $this->stateService->transicionar($ingreso->fresh(), EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->stateService->transicionar(
        $ingreso->fresh(),
        EstadoAccionPersonal::REGISTRADA,
        ['numero_contrato' => 'CT-2026-LARGO', 'remuneracion_propuesta' => 900]
    ))->toThrow(ReglaNegocioException::class, 'no puede extenderse más allá del 2028-07-31');
});

test('el plazo enviado en la propia aprobación también se comprueba', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $ingreso  = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));
    $ingreso  = $this->stateService->transicionar($ingreso, EstadoAccionPersonal::SUSCRITA);

    expect(fn () => $this->stateService->transicionar(
        $ingreso->fresh(),
        EstadoAccionPersonal::REGISTRADA,
        [
            'numero_contrato'        => 'CT-2026-APROB',
            'remuneracion_propuesta' => 900,
            'fecha_fin_propuesta'    => '2030-01-01',
        ]
    ))->toThrow(ReglaNegocioException::class, 'no puede extenderse más allá del 2028-07-31');
});

test('una ausencia admite un solo reemplazo a la vez', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $primero = ($this->servidor)();
    $this->service->registrar($primero->id, ($this->datosReemplazo)($comision));

    $segundo = ($this->servidor)();

    expect(fn () => $this->service->registrar(
        $segundo->id,
        ($this->datosReemplazo)($comision)
    ))->toThrow(ReglaNegocioException::class, 'ya tiene un reemplazo vigente');
});

test('se puede corregir a qué ausencia cubre un borrador mal enlazado', function () {
    [, $comisionA] = ($this->comisionRegistrada)();
    [, $comisionB] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $ingreso  = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comisionA));

    $this->service->actualizarBorrador($ingreso, ['cubre_movimiento_id' => $comisionB->id]);

    expect($ingreso->fresh()->cubre_movimiento_id)->toBe($comisionB->id);
});

test('reenlazar el borrador a una ausencia ya cubierta se rechaza igual que al crear', function () {
    [, $comisionA] = ($this->comisionRegistrada)();
    [, $comisionB] = ($this->comisionRegistrada)();

    // B ya tiene quien la cubra.
    $primero = ($this->servidor)();
    $this->service->registrar($primero->id, ($this->datosReemplazo)($comisionB));

    $segundo = ($this->servidor)();
    $ingreso = $this->service->registrar($segundo->id, ($this->datosReemplazo)($comisionA));

    expect(fn () => $this->service->actualizarBorrador($ingreso, [
        'cubre_movimiento_id' => $comisionB->id,
    ]))->toThrow(ReglaNegocioException::class, 'ya tiene un reemplazo vigente');

    expect($ingreso->fresh()->cubre_movimiento_id)->toBe($comisionA->id);
});

test('desenlazar el reemplazo desde el borrador siempre se permite', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $ingreso  = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));

    $this->service->actualizarBorrador($ingreso, ['cubre_movimiento_id' => null]);

    expect($ingreso->fresh()->cubre_movimiento_id)->toBeNull();
});

test('un servidor no puede cubrir su propia ausencia', function () {
    [$titular, $comision] = ($this->comisionRegistrada)();

    expect(fn () => $this->service->registrar(
        $titular->id,
        ($this->datosReemplazo)($comision)
    ))->toThrow(ReglaNegocioException::class, 'su propia ausencia');
});

// ── Listado con cobertura ───────────────────────────────────────

test('el listado muestra quién cubre la ausencia una vez materializado el contrato', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $ingreso  = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));
    $ingreso  = $this->stateService->transicionar($ingreso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($ingreso->fresh(), EstadoAccionPersonal::REGISTRADA, [
        'numero_contrato'        => 'CT-2026-COBER',
        'remuneracion_propuesta' => 900,
        'fecha_fin_propuesta'    => '2028-07-31',
    ]);

    $filas = $this->ausencias->listar(['fecha' => '2027-01-15']);

    expect($filas[0]['reemplazo'])->not->toBeNull()
        ->and($filas[0]['reemplazo']['servidor']['id'])->toBe($suplente->id)
        ->and($filas[0]['reemplazo']['numero_contrato'])->toBe('CT-2026-COBER');

    expect($this->ausencias->listar(['fecha' => '2027-01-15', 'cubiertas' => false]))->toBeEmpty()
        ->and($this->ausencias->listar(['fecha' => '2027-01-15', 'cubiertas' => true]))->toHaveCount(1);
});

test('un ingreso en borrador todavía no se muestra como cobertura', function () {
    [, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));

    $filas = $this->ausencias->listar(['fecha' => '2027-01-15']);

    expect($filas[0]['reemplazo'])->toBeNull();
});

test('la actividad laboral del suplente explica a quién está reemplazando', function () {
    [$titular, $comision] = ($this->comisionRegistrada)();

    $suplente = ($this->servidor)();
    $ingreso  = $this->service->registrar($suplente->id, ($this->datosReemplazo)($comision));
    $ingreso  = $this->stateService->transicionar($ingreso, EstadoAccionPersonal::SUSCRITA);
    $this->stateService->transicionar($ingreso->fresh(), EstadoAccionPersonal::REGISTRADA, [
        'numero_contrato'        => 'CT-2026-ACTIV',
        'remuneracion_propuesta' => 900,
    ]);

    $actividad = app(\App\Services\Expediente\ContratoServidorService::class)
        ->actividadLaboral($suplente->id, '2027-01-15');

    expect($actividad[0]['reemplaza_a'])->not->toBeNull()
        ->and($actividad[0]['reemplaza_a']['movimiento_id'])->toBe($comision->id)
        ->and($actividad[0]['reemplaza_a']['servidor'])->toContain($titular->apellido)
        ->and($actividad[0]['reemplaza_a']['hasta'])->toBe('2028-07-31');

    // El vínculo del titular no es un reemplazo: no debe llevar el enlace.
    $delTitular = app(\App\Services\Expediente\ContratoServidorService::class)
        ->actividadLaboral($titular->id, '2027-01-15');

    expect($delTitular[0]['reemplaza_a'])->toBeNull()
        ->and($delTitular[0]['situacion']['etiqueta'])
        ->toBe('Comisión de Servicios con Remuneración');
});

test('la ruta de ausencias temporales responde el listado', function () {
    ($this->comisionRegistrada)();

    $this->getJson('/api/v1/expediente/ausencias-temporales?fecha=2027-01-15')
        ->assertOk()
        ->assertJsonStructure([
            'datos' => [['id', 'etiqueta', 'desde', 'hasta', 'servidor' => ['id', 'nombre'], 'reemplazo']],
        ]);
});

/**
 * El filtro viaja como cadena en el query string. La regla 'boolean' de
 * Laravel no acepta "true"/"false", así que sin normalizar antes de validar la
 * petición se rechazaba con un 422 y el selector de reemplazos del formulario
 * de ingreso se quedaba vacío aunque hubiera ausencias sin cubrir.
 */
test('el filtro de cobertura acepta el booleano tal como lo envía el cliente', function () {
    ($this->comisionRegistrada)();

    $this->getJson('/api/v1/expediente/ausencias-temporales?fecha=2027-01-15&cubiertas=false')
        ->assertOk()
        ->assertJsonCount(1, 'datos');

    $this->getJson('/api/v1/expediente/ausencias-temporales?fecha=2027-01-15&cubiertas=true')
        ->assertOk()
        ->assertJsonCount(0, 'datos');

    // La forma numérica tiene que seguir funcionando igual.
    $this->getJson('/api/v1/expediente/ausencias-temporales?fecha=2027-01-15&cubiertas=0')
        ->assertOk()
        ->assertJsonCount(1, 'datos');
});
