<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSubrogacion;
use App\Enums\InstrumentoActo;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\Subrogacion;
use App\Models\User;
use App\Services\Expediente\FirmanteAccionPersonalService;
use App\Services\Expediente\MovimientoPersonalService;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\ReintegroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.1b del diseño de Acciones de Personal: las ausencias de las
| autoridades electas y su reemplazo (diseño, 9.14; TH 3 y 6). La licencia que
| concede el Consejo (COOTAD Art. 47 s), la subrogación por ley del
| Viceprefecto y su asunción definitiva (COOTAD Art. 51), y la constancia
| impresa del acto de autoridad.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->servicio = app(MovimientoPersonalService::class);
    $this->hoy = now()->toDateString();

    // La diferencia de la subrogación se imputa a la 510512.
    PartidaPresupuestaria::create([
        'codigo' => '510512', 'descripcion' => 'Subrogaciones',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $grupo = fn (string $codigo, float $rmu) => GrupoOcupacional::create([
        'grado_codigo' => $codigo, 'grado_numerico' => 10, 'grupo' => 'NJS', 'denominacion_generica' => $codigo,
        'rmu' => $rmu, 'regimen' => 'losep', 'activo' => true,
    ]);

    $this->prefectura = UnidadAdministrativa::create([
        'codigo' => 'PREF', 'nombre' => 'Prefectura Provincial', 'nivel' => 1, 'es_maxima_autoridad' => true,
    ]);
    $this->viceprefectura = UnidadAdministrativa::create(['codigo' => 'VICE', 'nombre' => 'Viceprefectura', 'nivel' => 1]);

    $this->puestoPrefecto = Puesto::create([
        'unidad_administrativa_id' => $this->prefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id' => $grupo('NJS-10', 5060)->id,
    ]);
    $this->puestoVice = Puesto::create([
        'unidad_administrativa_id' => $this->viceprefectura->id, 'plazas' => 1, 'es_jefe' => true,
        'grupo_ocupacional_id' => $grupo('NJS-7', 4048)->id,
    ]);

    $this->contador = 0;
    $this->autoridad = function (Puesto $puesto, float $rmu): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'            => str_pad((string) (8600000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'            => 'Autoridad',
            'apellido'          => 'Electa'.$this->contador,
            'regimen_laboral'   => 'losep',
            'tipo_nombramiento' => TipoNombramiento::ELECCION_POPULAR->value,
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => TipoNombramiento::ELECCION_POPULAR->value,
            'numero_contrato'          => 'CNE-'.$this->contador,
            'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
            'puesto_id'                => $puesto->id,
            'fecha_inicio'             => '2023-05-15',
            'fecha_fin'                => '2027-05-13',
            'remuneracion'             => $rmu,
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->prefecto = ($this->autoridad)($this->puestoPrefecto, 5060);
    $this->vice = ($this->autoridad)($this->puestoVice, 4048);

    $this->licencia = fn (Servidor $s, string $desde, string $hasta, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'             => 'licencia_consejo',
            'descripcion'       => 'Licencia concedida por el Consejo Provincial',
            'fecha_efectiva'    => $desde,
            'fecha_inicio'      => $desde,
            'fecha_fin'         => $hasta,
            'con_remuneracion'  => true,
            'resolucion_numero' => 'RES-CP-2026-014',
            ...$extra,
        ]
    );

    $this->registrar = function (int $id, array $suscribir = []): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA, $suscribir);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };

    $this->subrogacionDe = fn (MovimientoPersonal $origen) => MovimientoPersonal::where('movimiento_relacionado_id', $origen->id)
        ->where('tipo_movimiento', TipoMovimientoPersonal::SUBROGACION->value)
        ->first();
});

// ── La licencia del Consejo ─────────────────────────────────────

test('la licencia del Consejo es acto de autoridad, con o sin remuneración', function () {
    ($this->licencia)($this->vice, $this->hoy, now()->addDays(4)->toDateString(), ['con_remuneracion' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'con o sin remuneración'));

    ($this->licencia)($this->vice, $this->hoy, now()->addDays(4)->toDateString(), ['con_remuneracion' => false])
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'licencia_consejo')
        ->assertJsonPath('datos.instrumento', 'acto_autoridad')
        ->assertJsonPath('datos.con_remuneracion', false);
});

test('hasta 60 días acumulados en el año, salvo prórroga del Consejo', function () {
    ($this->licencia)($this->vice, '2026-03-01', '2026-04-19')->assertCreated(); // 50 días

    ($this->licencia)($this->vice, '2026-06-01', '2026-06-20') // 20 más: 70
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'En 2026') && str_contains($m, '70 días'));

    ($this->licencia)($this->vice, '2026-06-01', '2026-06-20', ['prorroga_causa' => 'enfermedad_catastrofica'])
        ->assertCreated()
        ->assertJsonPath('datos.prorroga_causa', 'enfermedad_catastrofica');

    // El tope es del año: en el siguiente se cuenta de nuevo.
    ($this->licencia)($this->vice, '2027-01-04', '2027-02-02')->assertCreated();
});

test('no se suscribe sin el documento de origen', function () {
    $id = ($this->licencia)($this->vice, $this->hoy, now()->addDays(2)->toDateString(), ['resolucion_numero' => null])
        ->assertCreated()
        ->json('datos.id');

    expect(fn () => $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class, 'documento de origen');
});

test('la autoridad que vuelve antes tiene su reintegro', function () {
    $licencia = ($this->registrar)(($this->licencia)($this->vice, now()->subDays(5)->toDateString(), now()->addDays(10)->toDateString())->json('datos.id'));

    $reintegro = app(ReintegroService::class)->preparar($licencia, [
        'fecha_regreso' => $this->hoy,
        'descripcion'   => 'Vuelve antes de terminar la licencia.',
    ]);

    expect($reintegro->tipo_movimiento)->toBe(TipoMovimientoPersonal::REINTEGRO)
        ->and($reintegro->instrumento)->toBe(InstrumentoActo::ACTO_AUTORIDAD);
});

// ── La subrogación por ley del Viceprefecto ─────────────────────

test('la licencia del Prefecto de más de tres días prepara la subrogación por ley del Viceprefecto', function () {
    $corta = ($this->registrar)(($this->licencia)($this->prefecto, '2026-11-02', '2026-11-04')->json('datos.id'));
    expect(($this->subrogacionDe)($corta))->toBeNull();

    $larga = ($this->registrar)(($this->licencia)($this->prefecto, '2026-11-10', '2026-11-20')->json('datos.id'));
    $subrogacion = ($this->subrogacionDe)($larga);

    expect($subrogacion)->not->toBeNull()
        ->and($subrogacion->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($subrogacion->servidor_id)->toBe($this->vice->id)
        ->and($subrogacion->instrumento)->toBe(InstrumentoActo::ACTO_AUTORIDAD)
        ->and($subrogacion->fecha_inicio->toDateString())->toBe('2026-11-10')
        ->and($subrogacion->fecha_fin->toDateString())->toBe('2026-11-20')
        ->and($subrogacion->resolucion_numero)->toBe('RES-CP-2026-014');
});

test('registrada, el Viceprefecto firma como autoridad nominadora «(S)»', function () {
    $licencia = ($this->registrar)(($this->licencia)($this->prefecto, $this->hoy, now()->addDays(10)->toDateString())->json('datos.id'));
    $acto = ($this->registrar)(($this->subrogacionDe)($licencia)->id, ['dictamen_presupuestario_ref' => 'CP-2026-031']);

    expect($acto->codigo_registro)->toStartWith('AU-')
        ->and(Subrogacion::where('movimiento_personal_id', $acto->id)->value('estado'))->toBe(EstadoSubrogacion::ACTIVA);

    $firma = app(FirmanteAccionPersonalService::class)->resolver(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, $this->hoy);

    expect($firma['servidor']?->id)->toBe($this->vice->id)
        ->and($firma['subrogado'])->toBeTrue();
});

test('anular la licencia anula la subrogación que salió de ella, si sigue en trámite', function () {
    $licencia = ($this->registrar)(($this->licencia)($this->prefecto, '2026-11-10', '2026-11-20')->json('datos.id'));
    $subrogacion = ($this->subrogacionDe)($licencia);

    $this->estados->transicionar($licencia->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Error en las fechas.']);

    expect($subrogacion->fresh()->estado)->toBe(EstadoAccionPersonal::ANULADA)
        ->and(Subrogacion::where('movimiento_personal_id', $subrogacion->id)->value('estado'))->toBe(EstadoSubrogacion::CANCELADA);
});

test('la subrogación por ley se registra también a mano, si la ausencia pasa de tres días', function () {
    $url = '/api/v1/expediente/autoridades-electas/subrogacion-por-ley';
    $datos = ['motivo' => 'vacaciones', 'resolucion_numero' => 'OF-PREF-2026-220'];

    $this->postJson($url, [...$datos, 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-03'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'pasa de tres días'));

    $id = $this->postJson($url, [...$datos, 'fecha_inicio' => '2026-12-01', 'fecha_fin' => '2026-12-12'])
        ->assertCreated()
        ->json('datos.movimiento_id');

    expect(MovimientoPersonal::findOrFail($id)->servidor_id)->toBe($this->vice->id);
});

// ── La asunción definitiva ──────────────────────────────────────

test('el cese anticipado del Prefecto prepara la asunción definitiva del Viceprefecto', function () {
    $cese = $this->servicio->registrar($this->prefecto->id, [
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'descripcion'        => 'Renuncia aceptada por el Consejo',
        'fecha_efectiva'     => $this->hoy,
        'resolucion_numero'  => 'RES-CP-2026-020',
    ]);
    $cese = ($this->registrar)($cese->id);

    $asuncion = MovimientoPersonal::where('movimiento_relacionado_id', $cese->id)
        ->where('tipo_movimiento', TipoMovimientoPersonal::ASUNCION_DEFINITIVA->value)
        ->firstOrFail();

    expect($asuncion->servidor_id)->toBe($this->vice->id)
        ->and($asuncion->puesto_destino_id)->toBe($this->puestoPrefecto->id)
        ->and($asuncion->fecha_efectiva->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($asuncion->instrumento)->toBe(InstrumentoActo::ACTO_AUTORIDAD);

    // Talento Humano completa la resolución del Consejo y lo registra.
    $this->servicio->actualizarBorrador($asuncion, ['resolucion_numero' => 'RES-CP-2026-021', 'fecha_efectiva' => $this->hoy]);
    ($this->registrar)($asuncion->id);

    $vigente = $this->vice->fresh('contratoVigente')->contratoVigente;

    expect($vigente->puesto_id)->toBe($this->puestoPrefecto->id)
        ->and((float) $vigente->remuneracion)->toBe(5060.0)
        ->and($vigente->fecha_fin->toDateString())->toBe('2027-05-13');

    // Ahora es el titular: firma como Prefecto, sin «(S)».
    $firma = app(FirmanteAccionPersonalService::class)->resolver(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA, $this->hoy);
    expect($firma['servidor']?->id)->toBe($this->vice->id)
        ->and($firma['subrogado'])->toBeFalse();

    // El cese no se anula con la asunción registrada; la asunción sí, y lo devuelve.
    expect(fn () => $this->estados->transicionar($cese->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'x']))
        ->toThrow(ReglaNegocioException::class, 'anúlela antes');

    $this->estados->transicionar($asuncion->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Se revisa.']);

    expect($this->vice->fresh('contratoVigente')->contratoVigente->puesto_id)->toBe($this->puestoVice->id)
        ->and((float) $this->vice->fresh('contratoVigente')->contratoVigente->remuneracion)->toBe(4048.0);
});

test('el fin del período no prepara asunción: termina también el del Viceprefecto', function () {
    ContratoServidor::where('servidor_id', $this->prefecto->id)->update(['fecha_fin' => now()->subDay()->toDateString()]);

    $this->artisan('sgth:acciones:aplicar-vigentes')->assertSuccessful();

    expect(MovimientoPersonal::where('tipo_movimiento', TipoMovimientoPersonal::ASUNCION_DEFINITIVA->value)->exists())->toBeFalse();
});

// ── Constancia, pantalla y catálogo ─────────────────────────────

test('la constancia del acto de autoridad lleva su número AU y la firma de Talento Humano', function () {
    $licencia = ($this->registrar)(($this->licencia)($this->vice, $this->hoy, now()->addDays(2)->toDateString())->json('datos.id'));

    expect($licencia->fecha_suscripcion)->not->toBeNull()
        ->and($licencia->firmante_autoridad_nombre)->toBeNull();

    $this->get("/api/v1/expediente/movimientos/{$licencia->id}/accion-personal-pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $html = view('pdf.expediente.constancia-autoridad', [
        'movimiento'         => $licencia->fresh(),
        'anulada'            => false,
        'servidor'           => $this->vice,
        'firmaTalentoHumano' => ['rotulo' => RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO->rotuloDocumento(), 'nombre' => null, 'cargo' => 'Director/a de Talento Humano'],
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Acto de autoridad electa')
        ->toContain($licencia->codigo_registro)
        ->toContain('Con remuneración')
        ->toContain('RES-CP-2026-014');
});

test('la pantalla enseña la licencia en curso y quién subroga', function () {
    $licencia = ($this->registrar)(($this->licencia)($this->prefecto, $this->hoy, now()->addDays(10)->toDateString())->json('datos.id'));
    ($this->registrar)(($this->subrogacionDe)($licencia)->id, ['dictamen_presupuestario_ref' => 'CP-2026-032']);

    $filas = collect($this->getJson('/api/v1/expediente/autoridades-electas')->assertOk()->json('datos'))->keyBy('servidor.id');

    expect($filas[$this->prefecto->id]['es_prefecto'])->toBeTrue()
        ->and($filas[$this->prefecto->id]['ausencia']['id'])->toBe($licencia->id)
        ->and($filas[$this->prefecto->id]['subrogacion']['rol'])->toBe('subrogado')
        ->and($filas[$this->vice->id]['subrogacion']['rol'])->toBe('subrogante');
});

test('el catálogo trae la licencia del Consejo para la autoridad, y la «según su régimen» ya no', function () {
    $clases = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk()->json('datos.clases'))
        ->keyBy('codigo');

    expect($clases['licencia_consejo']['nombramientos_elegibles'])->toBe(['eleccion_popular'])
        ->and($clases['licencia_consejo']['pide_datos_de_licencia_del_consejo'])->toBeTrue()
        ->and($clases['licencia_consejo']['pide_periodo'])->toBeTrue()
        ->and($clases['asuncion_definitiva']['se_crea_desde_formulario'])->toBeFalse()
        ->and($clases['licencia_sin_remuneracion']['nombramientos_elegibles'])->not->toContain('eleccion_popular');
});
