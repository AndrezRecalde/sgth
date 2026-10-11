<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\EstadoSumario;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Disciplinario\SancionDisciplinaria;
use App\Models\Disciplinario\Sumario;
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

/*
| Fase 2.6 del diseño de Acciones de Personal: la restitución (LOSEP 46; TH
| N15). Vuelve al servidor a su puesto por sentencia, deja sin efecto la
| cesación y no le quita la antigüedad.
|
| La restitución rige hoy: el registro decide con la fecha real.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-RES', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    // Una sola plaza: es lo que hace interesante al provisional.
    $this->puesto = Puesto::create([
        'codigo' => 'P-RES', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1,
    ]);

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->hoy = now()->toDateString();
    $this->contador = 0;

    $this->persona = function (string $apellido): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8900000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => $apellido,
            'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2015-01-05',
        ]);
    };

    /** Un permanente destituido el 30/06/2025, con su vínculo cerrado por la cesación. */
    $this->destituido = function (TipoNombramiento $nombramiento = TipoNombramiento::PERMANENTE): Servidor {
        $servidor = ($this->persona)('Destituido');

        $cesacion = MovimientoPersonal::create([
            'servidor_id'        => $servidor->id,
            'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => SubtipoMovimientoPersonal::DESTITUCION->value,
            'estado'             => EstadoAccionPersonal::REGISTRADA,
            'codigo_registro'    => 'AP-2025-'.str_pad((string) (100 + $this->contador), 4, '0', STR_PAD_LEFT),
            'fecha_registro'     => '2025-06-30',
            'descripcion'        => 'Destitución por sumario',
            'fecha_efectiva'     => '2025-06-30',
            'efecto_aplicado_en' => now(),
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'numero_contrato'          => 'NP-2015-001',
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2015-01-05',
            'fecha_fin'                => '2025-06-30',
            'remuneracion'             => 1500,
            'estado'                   => 'terminado',
            'movimiento_cierre_id'     => $cesacion->id,
        ]);

        return $servidor->fresh();
    };

    $this->ocupante = function (TipoNombramiento $nombramiento): Servidor {
        $servidor = ($this->persona)('Ocupante');

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2025-08-01',
            'estado'                   => 'vigente',
        ]);

        return $servidor;
    };

    $this->restituir = fn (Servidor $s, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                    => 'restitucion',
            'descripcion'              => 'Restitución por sentencia',
            'fecha_efectiva'           => $this->hoy,
            'causa_judicial_numero'    => '08281-2025-00123',
            'unidad_judicial'          => 'Tribunal Distrital de lo Contencioso Administrativo',
            'fecha_sentencia'          => '2026-08-01',
            'fecha_ejecutoria'         => '2026-09-01',
            'remuneraciones_dejadas_de_percibir' => 18000,
            'requiere_dictamen_medico' => false,
            ...$extra,
        ]
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };

    $this->anular = fn (MovimientoPersonal $m) => $this->estados->transicionar(
        $m->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Prueba de anulación.']
    );
});

// ── Qué deja sin efecto y a dónde vuelve ────────────────────────

test('deja sin efecto la cesación de su último vínculo y vuelve al mismo puesto', function () {
    $servidor = ($this->destituido)();
    $cesacion = MovimientoPersonal::where('servidor_id', $servidor->id)->sole();

    ($this->restituir)($servidor)
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'restitucion')
        ->assertJsonPath('datos.estado', 'borrador')
        ->assertJsonPath('datos.movimiento_relacionado_id', $cesacion->id)
        ->assertJsonPath('datos.relacionado.etiqueta', 'Destitución')
        ->assertJsonPath('datos.puesto_destino_id', $this->puesto->id)
        ->assertJsonPath('datos.tipo_nombramiento_propuesto', 'nombramiento_permanente')
        ->assertJsonPath('datos.numero_contrato', 'NP-2015-001')
        ->assertJsonPath('datos.causa_judicial_numero', '08281-2025-00123');

    $this->getJson("/api/v1/expediente/movimientos/{$cesacion->id}")
        ->assertOk()
        ->assertJsonPath('datos.restituida_por.estado', 'borrador');
});

test('el puesto y el nombramiento no se eligen: los que tenía', function () {
    $otro = Puesto::create(['codigo' => 'P-OTRO', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5]);

    ($this->restituir)(($this->destituido)(), [
        'puesto_destino_id'           => $otro->id,
        'tipo_nombramiento_propuesto' => 'servicios_ocasionales',
    ])->assertCreated()
        ->assertJsonPath('datos.puesto_destino_id', $this->puesto->id)
        ->assertJsonPath('datos.tipo_nombramiento_propuesto', 'nombramiento_permanente');
});

test('pide los cuatro datos de la causa y las remuneraciones [TH N15]', function () {
    ($this->restituir)(($this->destituido)(), [
        'causa_judicial_numero' => null,
        'remuneraciones_dejadas_de_percibir' => null,
    ])->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'el número de la causa judicial')
            && str_contains($m, 'las remuneraciones dejadas de percibir'));
});

test('las fechas de la sentencia tienen su orden', function () {
    $servidor = ($this->destituido)();

    ($this->restituir)($servidor, ['fecha_ejecutoria' => '2026-07-01'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fecha_ejecutoria'], 'errores');

    ($this->restituir)($servidor, ['fecha_ejecutoria' => now()->addDay()->toDateString()])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no puede ser futura'));

    ($this->restituir)($servidor, ['fecha_sentencia' => '2025-06-01', 'fecha_ejecutoria' => '2025-06-15'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'posterior a la cesación'));

    ($this->restituir)($servidor, ['fecha_efectiva' => '2026-08-15'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'desde la ejecutoría'));
});

test('es de un permanente, de quien no tiene vínculo, y una por cesación', function () {
    // Varios servidores en el mismo puesto: la plaza no es lo que se prueba.
    $this->puesto->update(['plazas' => 5]);

    ($this->restituir)(($this->destituido)(TipoNombramiento::PROVISIONAL))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'nombramiento permanente'));

    $vinculado = ($this->ocupante)(TipoNombramiento::PERMANENTE);
    ($this->restituir)($vinculado)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'vínculo vigente'));

    ($this->restituir)(($this->persona)('Sin historia'))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'No hay una cesación registrada'));

    $servidor = ($this->destituido)();
    ($this->restituir)($servidor)->assertCreated();
    ($this->restituir)($servidor)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ya tiene su restitución'));
});

// ── El efecto ───────────────────────────────────────────────────

test('al registrarse crea el vínculo y la antigüedad no se corta', function () {
    $servidor = ($this->destituido)();

    $restitucion = ($this->registrar)(($this->restituir)($servidor)->json('datos.id'));

    $vigente = $servidor->fresh('contratoVigente')->contratoVigente;

    expect($restitucion->efecto_aplicado_en)->not->toBeNull()
        ->and($vigente->puesto_id)->toBe($this->puesto->id)
        ->and($vigente->tipo_nombramiento)->toBe(TipoNombramiento::PERMANENTE)
        ->and($vigente->movimiento_origen_id)->toBe($restitucion->id)
        ->and($servidor->fresh()->inicioDelServicioContinuo())->toBe('2015-01-05');
});

test('un ingreso tras el mismo hueco sí la reinicia: la diferencia es la sentencia', function () {
    $servidor = ($this->destituido)();

    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => 'nombramiento_permanente',
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->puesto->id,
        'fecha_inicio'             => $this->hoy,
        'estado'                   => 'vigente',
    ]);

    expect($servidor->fresh()->inicioDelServicioContinuo())->toBe($this->hoy);
});

test('la sentencia deja sin efecto la sanción de la destitución, y anularla la devuelve', function () {
    $servidor = ($this->destituido)();
    $cesacion = MovimientoPersonal::where('servidor_id', $servidor->id)->sole();

    $sumario = Sumario::create([
        'servidor_id' => $servidor->id, 'motivo' => 'Sumario', 'estado' => EstadoSumario::CERRADO,
        'fecha_apertura' => '2025-03-01', 'fecha_resolucion' => '2025-06-20',
    ]);
    $sancion = SancionDisciplinaria::create([
        'sumario_id' => $sumario->id, 'movimiento_personal_id' => $cesacion->id,
        'tipo_falta' => 'grave', 'tipo_sancion' => 'destitucion', 'fecha_efectiva' => '2025-06-30',
    ]);

    $restitucion = ($this->registrar)(($this->restituir)($servidor)->json('datos.id'));

    expect($sancion->fresh()->estaSinEfecto())->toBeTrue()
        ->and($sancion->fresh()->motivo_anulacion)->toContain('08281-2025-00123')
        ->and($sancion->fresh()->sin_efecto_por_movimiento_id)->toBe($restitucion->id)
        // El sumario no se mueve: lo decidió un juez.
        ->and($sumario->fresh()->estado)->toBe(EstadoSumario::CERRADO);

    ($this->anular)($restitucion);

    expect($sancion->fresh()->estaSinEfecto())->toBeFalse()
        ->and($servidor->fresh('contratoVigente')->contratoVigente)->toBeNull();
});

// ── La plaza y el provisional ───────────────────────────────────

test('si la ocupa un provisional, se le prepara la remoción y va primero', function () {
    $servidor = ($this->destituido)();
    $provisional = ($this->ocupante)(TipoNombramiento::PROVISIONAL);

    $id = ($this->restituir)($servidor)->assertCreated()->json('datos.id');

    $remocion = MovimientoPersonal::where('servidor_id', $provisional->id)->sole();

    expect($remocion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::REMOCION)
        ->and($remocion->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($remocion->movimiento_relacionado_id)->toBe($id)
        ->and($remocion->fecha_efectiva->toDateString())->toBe(now()->subDay()->toDateString());

    expect(fn () => ($this->registrar)($id))
        ->toThrow(ReglaNegocioException::class, 'Registre antes la remoción');

    ($this->registrar)($remocion->id);
    MovimientoPersonal::findOrFail($id)->update(['estado' => EstadoAccionPersonal::BORRADOR]);
    ($this->registrar)($id);

    expect($provisional->fresh('contratoVigente')->contratoVigente)->toBeNull()
        ->and($servidor->fresh('contratoVigente')->contratoVigente)->not->toBeNull();
});

test('anular la restitución anula la remoción en borrador', function () {
    $servidor = ($this->destituido)();
    $provisional = ($this->ocupante)(TipoNombramiento::PROVISIONAL);

    $restitucion = MovimientoPersonal::findOrFail(($this->restituir)($servidor)->json('datos.id'));

    ($this->anular)($restitucion);

    expect(MovimientoPersonal::where('servidor_id', $provisional->id)->sole()->estado)
        ->toBe(EstadoAccionPersonal::ANULADA);
});

test('si la ocupa un permanente, no cabe', function () {
    $servidor = ($this->destituido)();
    ($this->ocupante)(TipoNombramiento::PERMANENTE);

    ($this->restituir)($servidor)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ningún provisional'));

    expect(MovimientoPersonal::where('tipo_movimiento', 'restitucion')->exists())->toBeFalse();
});

// ── El borrador ─────────────────────────────────────────────────

test('corregir el borrador no deja la causa vacía, y la remoción sigue a la fecha', function () {
    $servidor = ($this->destituido)();
    $provisional = ($this->ocupante)(TipoNombramiento::PROVISIONAL);
    $id = ($this->restituir)($servidor, ['fecha_efectiva' => '2026-09-01'])->json('datos.id');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['causa_judicial_numero' => ''])
        ->assertStatus(422);

    // Como lo manda el formulario: con la contratación vacía, que no se toca.
    $this->putJson("/api/v1/expediente/movimientos/{$id}", [
        'fecha_efectiva'  => '2026-09-15',
        'numero_contrato' => null,
        'puede_marcar'    => null,
    ])->assertOk()
        ->assertJsonPath('datos.numero_contrato', 'NP-2015-001');

    expect(MovimientoPersonal::where('servidor_id', $provisional->id)->sole()->fecha_efectiva->toDateString())
        ->toBe('2026-09-14');
});

// ── Catálogo y documento ────────────────────────────────────────

test('el catálogo la ofrece a quien no tiene vínculo y pide la causa', function () {
    $clase = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos.clases'))
        ->firstWhere('codigo', 'restitucion');

    expect($clase['requiere_vinculo'])->toBeFalse()
        ->and($clase['se_crea_desde_formulario'])->toBeTrue()
        ->and($clase['pide_datos_judiciales'])->toBeTrue()
        ->and($clase['familia'])->toBe('ingreso');
});

test('el documento dice qué deja sin efecto y la sentencia', function () {
    $servidor = ($this->destituido)();
    $cesacion = MovimientoPersonal::where('servidor_id', $servidor->id)->sole();
    $restitucion = MovimientoPersonal::findOrFail(($this->restituir)($servidor)->json('datos.id'));

    $firma = fn (RolFirmaAccionPersonal $rol) => ['rotulo' => $rol->rotuloDocumento(), 'nombre' => null, 'cargo' => null];

    $html = view('pdf.expediente.accion-personal', [
        'movimiento'         => $restitucion,
        'servidor'           => $servidor,
        'firmaAutoridad'     => $firma(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
        'firmaTalentoHumano' => $firma(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Restitución')
        ->and($html)->toContain('Deja sin efecto')
        ->and($html)->toContain("Destitución ({$cesacion->codigo_registro})")
        ->and($html)->toContain('08281-2025-00123')
        ->and($html)->toContain('ejecutoriada el')
        ->and($html)->toContain('01/09/2026')
        ->and($html)->toContain('18,000.00');
});
