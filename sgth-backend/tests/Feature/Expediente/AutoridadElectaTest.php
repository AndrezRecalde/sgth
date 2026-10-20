<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoEventoVinculo;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\EventoVinculo;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\AutoridadElectaService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.1 del diseño de Acciones de Personal: el período de las autoridades
| electas (diseño, 9.14; TH 1–7). El Prefecto y el Viceprefecto empiezan su
| período con la credencial del CNE, sin acción de personal; se cierra solo en
| su fecha, la reelección abre el siguiente sin cortar la antigüedad, y su cese
| lleva el documento de origen.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->hoy = now()->toDateString();

    $this->prefectura = UnidadAdministrativa::create(['codigo' => 'PREF', 'nombre' => 'Prefectura Provincial', 'nivel' => 1]);
    $this->prefecto = Puesto::create([
        'unidad_administrativa_id' => $this->prefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id'     => GrupoOcupacional::create([
            'grado_codigo' => 'NJS-10', 'grado_numerico' => 10, 'grupo' => 'NJS', 'denominacion_generica' => 'Prefecto/a',
            'rmu' => 5060, 'regimen' => 'losep', 'activo' => true,
        ])->id,
    ]);

    $this->contador = 0;
    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8500000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Autoridad',
            'apellido'        => 'Electa'.$this->contador,
            'regimen_laboral' => 'losep',
        ]);
    };

    /** Una autoridad ya en funciones, como la de la carga inicial. */
    $this->enFunciones = function (string $desde, ?string $hasta, TipoNombramiento $nombramiento = TipoNombramiento::ELECCION_POPULAR): array {
        $servidor = ($this->persona)();

        $contrato = ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'numero_contrato'          => 'CRED-'.$this->contador,
            'unidad_administrativa_id' => $this->prefectura->id,
            'puesto_id'                => $this->prefecto->id,
            'fecha_inicio'             => $desde,
            'fecha_fin'                => $hasta,
            'remuneracion'             => 5060,
            'estado'                   => 'vigente',
        ]);

        return [$servidor->fresh('contratoVigente'), $contrato];
    };

    $this->iniciar = fn (Servidor $s, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                 => 'inicio_periodo',
            'causal'                => 'eleccion',
            'descripcion'           => 'Posesión del Prefecto Provincial electo',
            'fecha_efectiva'        => $this->hoy,
            'unidad_destino_id'     => $this->prefectura->id,
            'puesto_destino_id'     => $this->prefecto->id,
            'credencial_cne_numero' => 'CNE-2026-0147',
            'credencial_cne_fecha'  => now()->subDays(20)->toDateString(),
            'acta_posesion_numero'  => 'ACTA-POS-001',
            'fecha_fin_propuesta'   => now()->addYearsNoOverflow(4)->subDay()->toDateString(),
            ...$extra,
        ]
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── El inicio del período ───────────────────────────────────────

test('el inicio del período crea el vínculo de la autoridad, sin acción de personal', function () {
    $servidor = ($this->persona)();

    $id = ($this->iniciar)($servidor)
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'inicio_periodo')
        ->assertJsonPath('datos.instrumento', 'acto_autoridad')
        ->json('datos.id');

    $inicio = ($this->registrar)($id);
    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($inicio->codigo_registro)->toStartWith('AU-'.now()->year.'-')
        // No la firma la autoridad nominadora: el Prefecto lo es. Su constancia
        // la firma Talento Humano (fase 4.1b).
        ->and($inicio->firmante_autoridad_nombre)->toBeNull()
        ->and($vigente->tipo_nombramiento)->toBe(TipoNombramiento::ELECCION_POPULAR)
        ->and($vigente->numero_contrato)->toBe('CNE-2026-0147')
        ->and($vigente->fecha_fin->toDateString())->toBe(now()->addYearsNoOverflow(4)->subDay()->toDateString())
        ->and((float) $vigente->remuneracion)->toBe(5060.0)
        ->and($vigente->puede_marcar)->toBeFalse()
        ->and($servidor->fresh()->tipo_nombramiento)->toBe(TipoNombramiento::ELECCION_POPULAR);
});

test('pide la credencial del CNE y el fin del período, de hasta cuatro años', function () {
    ($this->iniciar)(($this->persona)(), ['credencial_cne_numero' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'credencial del CNE'));

    ($this->iniciar)(($this->persona)(), ['fecha_fin_propuesta' => now()->addYears(4)->addMonth()->toDateString()])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'cuatro años'));

    ($this->iniciar)(($this->persona)(), ['fecha_fin_propuesta' => now()->subDay()->toDateString()])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'termina después de empezar'));
});

test('al ingreso no se le registra la elección popular', function () {
    $this->postJson('/api/v1/expediente/servidores/'.($this->persona)()->id.'/movimientos', [
        'clase'                       => 'ingreso',
        'descripcion'                 => 'Ingreso',
        'fecha_efectiva'              => $this->hoy,
        'tipo_nombramiento_propuesto' => 'eleccion_popular',
        'unidad_destino_id'           => $this->prefectura->id,
        'puesto_destino_id'           => $this->prefecto->id,
    ])->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Inicio del período'));
});

test('no ocupa plaza: el entrante y el saliente coinciden el día del cambio', function () {
    ($this->enFunciones)('2023-05-15', $this->hoy);

    $id = ($this->iniciar)(($this->persona)())->assertCreated()->json('datos.id');

    expect(($this->registrar)($id)->efecto_aplicado_en)->not->toBeNull()
        ->and($this->prefecto->fresh()->plazasOcupadas())->toBe(0);
});

test('a quien sigue en funciones no se le abre otro período encima', function () {
    [$sinFin] = ($this->enFunciones)('2023-05-15', null);
    ($this->iniciar)($sinFin)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'fíjela'));

    [$abierto] = ($this->enFunciones)('2023-05-15', now()->addMonth()->toDateString());
    ($this->iniciar)($abierto)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'el nuevo empieza después'));

    [$permanente] = ($this->enFunciones)('2020-01-01', null, TipoNombramiento::PERMANENTE);
    ($this->iniciar)($permanente)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Cesación de Funciones'));
});

// ── El fin del período y la reelección ──────────────────────────

test('el período se cierra solo al terminar', function () {
    [$servidor, $contrato] = ($this->enFunciones)('2023-05-15', now()->subDay()->toDateString());

    $this->artisan('sgth:acciones:aplicar-vigentes')->assertSuccessful();

    $fin = MovimientoPersonal::where('servidor_id', $servidor->id)
        ->where('subtipo_movimiento', SubtipoMovimientoPersonal::FIN_DEL_PERIODO->value)
        ->firstOrFail();

    expect($fin->estado)->toBe(EstadoAccionPersonal::REGISTRADA)
        ->and($fin->instrumento)->toBe(InstrumentoActo::ACTO_AUTORIDAD)
        ->and($fin->codigo_registro)->toStartWith('AU-')
        ->and($fin->fecha_efectiva->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($contrato->fresh()->estado->value)->toBe('terminado')
        ->and($contrato->fresh()->fecha_fin->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($servidor->fresh()->estado)->toBeFalse();

    // Una sola vez: el comando del día siguiente no lo repite.
    $this->artisan('sgth:acciones:aplicar-vigentes')->assertSuccessful();
    expect(MovimientoPersonal::where('servidor_id', $servidor->id)->count())->toBe(1);
});

test('la reelección empieza cuando termina el período, sin cortar la antigüedad', function () {
    $termina = now()->addDays(10);
    [$servidor, $anterior] = ($this->enFunciones)('2023-05-15', $termina->toDateString());

    // Se registra antes de que termine el período actual, y espera su fecha.
    $id = ($this->iniciar)($servidor, [
        'fecha_efectiva'        => $termina->copy()->addDay()->toDateString(),
        'credencial_cne_numero' => 'CNE-2027-0003',
        'fecha_fin_propuesta'   => $termina->copy()->addYearsNoOverflow(4)->toDateString(),
    ])->assertCreated()->json('datos.id');
    $reeleccion = ($this->registrar)($id);

    expect($reeleccion->efecto_aplicado_en)->toBeNull();

    // El día en que empieza, el comando cierra el anterior y abre el nuevo.
    $this->artisan('sgth:acciones:aplicar-vigentes', ['--fecha' => $termina->copy()->addDay()->toDateString()])
        ->assertSuccessful();

    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($anterior->fresh()->estado->value)->toBe('terminado')
        ->and($vigente->id)->not->toBe($anterior->id)
        ->and($vigente->numero_contrato)->toBe('CNE-2027-0003')
        ->and($servidor->fresh()->inicioDelServicioContinuo())->toBe('2023-05-15');
});

test('si la reelección se registra cuando el período ya terminó, lo cierra antes', function () {
    [$servidor, $anterior] = ($this->enFunciones)('2023-05-15', now()->subDay()->toDateString());

    $id = ($this->iniciar)($servidor)->assertCreated()->json('datos.id');
    ($this->registrar)($id);

    expect($anterior->fresh()->estado->value)->toBe('terminado')
        ->and($servidor->fresh('contratoVigente')->contratoVigente->numero_contrato)->toBe('CNE-2026-0147')
        ->and($servidor->fresh()->inicioDelServicioContinuo())->toBe('2023-05-15');
});

// ── El cese de la autoridad ─────────────────────────────────────

test('el cese de la autoridad tiene sus causales y lleva su documento de origen', function () {
    [$servidor, $contrato] = ($this->enFunciones)('2023-05-15', now()->addYear()->toDateString());
    $url = "/api/v1/expediente/servidores/{$servidor->id}/movimientos";
    $cese = fn (string $causal, ?string $documento) => $this->postJson($url, [
        'clase'             => 'cesacion',
        'causal'            => $causal,
        'descripcion'       => 'Cese de la autoridad',
        'fecha_efectiva'    => $this->hoy,
        'resolucion_numero' => $documento,
    ]);

    // Las de la LOSEP no le valen, ni el fin del período, que lo pone el sistema.
    $cese('destitucion', 'X')->assertStatus(422);
    $cese('fin_del_periodo', 'X')->assertStatus(422);

    $cese('revocatoria_del_mandato', null)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'documento de origen'));

    $id = $cese('revocatoria_del_mandato', 'CNE-RES-2026-88')
        ->assertCreated()
        ->assertJsonPath('datos.instrumento', 'acto_autoridad')
        ->json('datos.id');

    ($this->registrar)($id);

    expect($contrato->fresh()->estado->value)->toBe('terminado');
});

test('la licencia de la autoridad también es acto de autoridad', function () {
    [$servidor] = ($this->enFunciones)('2023-05-15', now()->addYear()->toDateString());

    // Desde la fase 4.1b es la licencia del Consejo, no la «según su régimen».
    $this->postJson("/api/v1/expediente/servidores/{$servidor->id}/movimientos", [
        'clase'             => 'licencia_consejo',
        'descripcion'       => 'Licencia concedida por el Consejo Provincial',
        'fecha_efectiva'    => $this->hoy,
        'fecha_inicio'      => $this->hoy,
        'fecha_fin'         => now()->addDays(5)->toDateString(),
        'con_remuneracion'  => true,
        'resolucion_numero' => 'RES-CP-2026-001',
    ])->assertCreated()
        ->assertJsonPath('datos.instrumento', 'acto_autoridad');
});

// ── El fin del período de quien ya estaba ───────────────────────

test('a la autoridad que ya estaba se le fija el fin del período', function () {
    [$servidor, $contrato] = ($this->enFunciones)('2023-05-15', null);
    $url = "/api/v1/expediente/contratos/{$contrato->id}/fin-del-periodo";

    $this->postJson($url, ['fecha_fin' => '2027-06-30'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'cuatro años'));

    $this->postJson($url, ['fecha_fin' => '2027-05-13'])->assertOk();

    expect($contrato->fresh()->fecha_fin->toDateString())->toBe('2027-05-13')
        ->and(EventoVinculo::where('contrato_servidor_id', $contrato->id)
            ->where('tipo', TipoEventoVinculo::FIN_DEL_PERIODO_FIJADO->value)->exists())->toBeTrue();

    // El de un período que empezó con su acto lo dice la credencial.
    $nuevo = ($this->persona)();
    ($this->registrar)(($this->iniciar)($nuevo)->json('datos.id'));
    $this->postJson('/api/v1/expediente/contratos/'.$nuevo->fresh('contratoVigente')->contratoVigente->id.'/fin-del-periodo', [
        'fecha_fin' => now()->addYear()->toDateString(),
    ])->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'anule el inicio'));
});

// ── Pantalla, catálogo y documento ──────────────────────────────

test('la pantalla enseña a las autoridades en funciones y su período', function () {
    [$servidor, $contrato] = ($this->enFunciones)('2023-05-15', null);

    $fila = collect($this->getJson('/api/v1/expediente/autoridades-electas')->assertOk()->json('datos'))
        ->firstWhere('contrato_id', $contrato->id);

    expect($fila['servidor']['id'])->toBe($servidor->id)
        ->and($fila['desde'])->toBe('2023-05-15')
        ->and($fila['hasta'])->toBeNull()
        ->and($fila['puede_fijar_fin'])->toBeTrue()
        ->and($fila['cese'])->toBeNull();
});

test('el catálogo trae el inicio del período, y los actos de la autoridad sin firmantes', function () {
    $clases = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk()->json('datos.clases'))
        ->keyBy('codigo');

    expect($clases['inicio_periodo']['requiere_vinculo'])->toBeFalse()
        ->and($clases['inicio_periodo']['nombramientos_elegibles'])->toBe(['eleccion_popular'])
        ->and($clases['inicio_periodo']['pide_datos_del_periodo'])->toBeTrue()
        ->and($clases['inicio_periodo']['acto_de_autoridad_para'])->toBe(['eleccion_popular'])
        ->and($clases['cesacion']['acto_de_autoridad_para'])->toBe(['eleccion_popular'])
        ->and($clases['ingreso']['acto_de_autoridad_para'])->toBe([])
        ->and(collect($clases['cesacion']['causales'])->pluck('codigo')->all())
        ->toContain('revocatoria_del_mandato', 'remocion_por_el_consejo')
        ->not->toContain('fin_del_periodo');
});

test('no tiene acción de personal: su documento es la constancia', function () {
    $servidor = ($this->persona)();
    $inicio = ($this->registrar)(($this->iniciar)($servidor)->json('datos.id'));

    // La constancia, con su número AU (fase 4.1b).
    $respuesta = $this->get("/api/v1/expediente/movimientos/{$inicio->id}/accion-personal-pdf")->assertOk();

    expect($respuesta->headers->get('content-disposition'))->toContain('constancia_'.$inicio->codigo_registro);
});
