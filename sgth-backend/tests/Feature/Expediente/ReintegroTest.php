<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Enums\RolFirmaAccionPersonal;
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

/*
| Fase 2.4 del diseño de Acciones de Personal: el reintegro (LOSEP 32; TH 17 y
| 18). Cierra la comisión o la licencia cuando el servidor vuelve, saca al
| reemplazo, y la ausencia ya no se anula para deshacerla.
|
| Las fechas son relativas a hoy: el registro decide con la fecha real.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-REI', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-REI', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5,
    ]);

    $this->estados = app(MovimientoPersonalStateService::class);

    $this->hoy     = now()->toDateString();
    $this->ayer    = now()->subDay()->toDateString();
    $this->inicio  = now()->subMonths(2)->toDateString();
    $this->fin     = now()->addMonths(6)->toDateString();

    $this->contador = 0;

    $this->servidor = function (TipoNombramiento $nombramiento = TipoNombramiento::PERMANENTE, ?MovimientoPersonal $cubre = null): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Servidor',
            'apellido'        => 'Reintegro'.$this->contador,
            'regimen_laboral' => 'losep',
            'puesto_id'       => $this->puesto->id,
            'unidad_administrativa_id' => $this->unidad->id,
            'fecha_ingreso_institucion' => '2015-01-05',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $cubre?->fecha_inicio?->toDateString() ?? '2015-01-05',
            'fecha_fin'                => $cubre?->fecha_fin?->toDateString()
                ?? ($nombramiento->exigePlazo() ? now()->addYear()->toDateString() : null),
            'cubre_movimiento_id'      => $cubre?->id,
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    /** Una comisión ya registrada y en curso. */
    $this->ausencia = fn (Servidor $titular, ?string $inicio = null, ?string $fin = null, array $extra = []) => MovimientoPersonal::create([
        'servidor_id'         => $titular->id,
        'tipo_movimiento'     => TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO->value,
        'subtipo_movimiento'  => SubtipoMovimientoPersonal::COMISION_CON_REMUNERACION->value,
        'estado'              => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro'     => 'AP-2020-'.str_pad((string) (++$this->contador), 4, '0', STR_PAD_LEFT),
        'descripcion'         => 'Comisión de servicios en el Ministerio',
        'institucion_destino' => 'Ministerio del Trabajo',
        'fecha_efectiva'      => $inicio ?? $this->inicio,
        'fecha_inicio'        => $inicio ?? $this->inicio,
        'fecha_fin'           => $fin ?? $this->fin,
        'efecto_aplicado_en'  => now(),
        'puesto_origen_id'    => $this->puesto->id,
        'unidad_origen_id'    => $this->unidad->id,
        ...$extra,
    ]);

    $this->reintegrar = fn (MovimientoPersonal $ausencia, string $regreso) => $this->postJson(
        "/api/v1/expediente/movimientos/{$ausencia->id}/reintegro",
        ['fecha_regreso' => $regreso, 'descripcion' => 'El servidor vuelve de su comisión.']
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };

    $this->anular = fn (MovimientoPersonal $m) => $this->estados->transicionar(
        $m->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Prueba de anulación.']
    );
});

// ── Dónde nace y qué valida ─────────────────────────────────────

test('el reintegro nace de la ausencia, en borrador, enlazado a ella', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());

    ($this->reintegrar)($ausencia, $this->hoy)
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'reintegro')
        ->assertJsonPath('datos.etiqueta', 'Reintegro')
        ->assertJsonPath('datos.estado', 'borrador')
        ->assertJsonPath('datos.movimiento_relacionado_id', $ausencia->id)
        ->assertJsonPath('datos.relacionado.codigo_registro', $ausencia->codigo_registro)
        ->assertJsonPath('datos.editable_en_formulario', false);
});

test('no se crea desde el formulario genérico', function () {
    $titular = ($this->servidor)();

    $this->postJson("/api/v1/expediente/servidores/{$titular->id}/movimientos", [
        'clase'          => 'reintegro',
        'descripcion'    => 'Reintegro sin ausencia',
        'fecha_efectiva' => $this->hoy,
    ])->assertStatus(422);
});

test('la ausencia tiene que estar registrada y sin otro reintegro', function () {
    $titular = ($this->servidor)();

    $borrador = ($this->ausencia)($titular, extra: ['estado' => EstadoAccionPersonal::BORRADOR, 'codigo_registro' => null]);
    ($this->reintegrar)($borrador, $this->hoy)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'tiene que estar registrada'));

    $ausencia = ($this->ausencia)($titular);
    ($this->reintegrar)($ausencia, $this->hoy)->assertCreated();
    ($this->reintegrar)($ausencia, $this->hoy)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'reintegro en trámite'));
});

test('solo se reintegra desde una comisión o una licencia', function () {
    $titular = ($this->servidor)();

    $cesacion = MovimientoPersonal::create([
        'servidor_id'        => $titular->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::RENUNCIA->value,
        'estado'             => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro'    => 'AP-2020-9001',
        'descripcion'        => 'Renuncia',
        'fecha_efectiva'     => $this->ayer,
    ]);

    ($this->reintegrar)($cesacion, $this->hoy)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'comisión de servicios o con licencia'));
});

test('el regreso cae después del inicio y a más tardar el día siguiente al fin', function () {
    $ausencia = ($this->ausencia)(($this->servidor)(), '2026-03-01', '2026-08-31');

    ($this->reintegrar)($ausencia, '2026-03-01')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'posterior al inicio'));

    ($this->reintegrar)($ausencia, '2026-09-02')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'a más tardar el 01/09/2026'));

    ($this->reintegrar)($ausencia, '2026-09-01')->assertCreated();
});

test('nadie prepara su propio reintegro', function () {
    $titular = ($this->servidor)();
    $ausencia = ($this->ausencia)($titular);

    $this->director->forceFill(['servidor_id' => $titular->id])->save();

    ($this->reintegrar)($ausencia, $this->hoy)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'sobre usted mismo'));
});

test('quien no prepara acciones no reintegra', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());

    $this->actingAs(User::factory()->create(), 'sanctum');

    ($this->reintegrar)($ausencia, $this->hoy)->assertForbidden();
});

test('es de quien puede tener una ausencia: permanentes, obreros y dignatarios', function () {
    $elegibles = array_values(array_map(
        fn (TipoNombramiento $n) => $n->value,
        array_filter(TipoNombramiento::cases(), fn (TipoNombramiento $n) => TipoMovimientoPersonal::REINTEGRO->elegiblePara($n)),
    ));
    sort($elegibles);

    expect($elegibles)->toBe(['codigo_trabajo', 'eleccion_popular', 'nombramiento_permanente']);
});

// ── El fin de la ausencia ───────────────────────────────────────

test('al surtir efecto la ausencia termina el día anterior al regreso', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());

    $id = ($this->reintegrar)($ausencia, $this->hoy)->json('datos.id');

    // En borrador todavía no cierra nada.
    expect($ausencia->fresh()->finEfectivo()->toDateString())->toBe($this->fin);

    $reintegro = ($this->registrar)($id);

    expect($reintegro->efecto_aplicado_en)->not->toBeNull()
        ->and($ausencia->fresh()->finEfectivo()->toDateString())->toBe($this->ayer);

    $this->getJson('/api/v1/expediente/ausencias-temporales')
        ->assertOk()
        ->assertJsonCount(0, 'datos');

    $this->getJson("/api/v1/expediente/ausencias-temporales?fecha={$this->ayer}")
        ->assertOk()
        ->assertJsonPath('datos.0.id', $ausencia->id)
        ->assertJsonPath('datos.0.hasta', $this->ayer)
        ->assertJsonPath('datos.0.reintegro.codigo_registro', $reintegro->codigo_registro);
});

test('el que rige más tarde deja la ausencia vigente hasta la víspera del regreso', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    $regreso = now()->addDays(10)->toDateString();
    $vispera = now()->addDays(9)->toDateString();

    $reintegro = ($this->registrar)(($this->reintegrar)($ausencia, $regreso)->json('datos.id'));

    expect($reintegro->pendienteDeVigencia())->toBeTrue();

    $this->getJson('/api/v1/expediente/ausencias-temporales')
        ->assertOk()
        ->assertJsonPath('datos.0.hasta', $vispera)
        ->assertJsonPath('datos.0.dias_restantes', 9)
        ->assertJsonPath('datos.0.reintegro.fecha_regreso', $regreso);

    $this->getJson("/api/v1/expediente/ausencias-temporales?fecha={$regreso}")
        ->assertOk()
        ->assertJsonCount(0, 'datos');

    $resultado = $this->estados->aplicarVigentes($regreso);

    expect(collect($resultado['aplicadas'])->pluck('id'))->toContain($reintegro->id);
});

test('el reemplazo no se alarga más allá del regreso del titular', function () {
    $titular = ($this->servidor)();
    $ausencia = ($this->ausencia)($titular);
    $regreso = now()->addDays(10)->toDateString();

    ($this->registrar)(($this->reintegrar)($ausencia, $regreso)->json('datos.id'));

    $suplente = Servidor::create([
        'cedula' => '8899999999', 'nombre' => 'Suplente', 'apellido' => 'Nuevo',
        'regimen_laboral' => 'losep', 'fecha_ingreso_institucion' => $this->hoy,
    ]);

    expect(fn () => app(\App\Services\Expediente\MovimientoPersonalService::class)->registrar($suplente->id, [
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'puesto_destino_id'           => $this->puesto->id,
        'unidad_destino_id'           => $this->unidad->id,
        'requiere_dictamen_medico'    => false,
        'descripcion'                 => 'Reemplazo',
        'fecha_efectiva'              => $this->hoy,
        'fecha_fin_propuesta'         => $this->fin,
        'cubre_movimiento_id'         => $ausencia->id,
    ]))->toThrow(ReglaNegocioException::class, 'más allá del '.now()->addDays(9)->toDateString());
});

// ── La salida del reemplazo [TH 18] ─────────────────────────────

test('al surtir efecto, al ocasional que cubría se le prepara la salida en borrador', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    $suplente = ($this->servidor)(TipoNombramiento::SERVICIOS_OCASIONALES, $ausencia);

    $reintegro = ($this->registrar)(($this->reintegrar)($ausencia, $this->hoy)->json('datos.id'));

    $salida = MovimientoPersonal::where('servidor_id', $suplente->id)->sole();

    expect($salida->tipo_movimiento)->toBe(TipoMovimientoPersonal::CESACION_FUNCIONES)
        ->and($salida->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::FIN_DEL_PLAZO)
        ->and($salida->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($salida->fecha_efectiva->toDateString())->toBe($this->ayer)
        ->and($salida->movimiento_relacionado_id)->toBe($reintegro->id)
        ->and($salida->descripcion)->toContain($reintegro->codigo_registro);

    // El contrato sigue vigente: lo cierra la cesación cuando se registre.
    expect($suplente->contratoVigente->fresh()->estado->value)->toBe('vigente');
});

test('al profesional que cubría, por contrato finalizado', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    $suplente = ($this->servidor)(TipoNombramiento::SERVICIOS_PROFESIONALES, $ausencia);

    ($this->registrar)(($this->reintegrar)($ausencia, $this->hoy)->json('datos.id'));

    expect(MovimientoPersonal::where('servidor_id', $suplente->id)->sole()->subtipo_movimiento)
        ->toBe(SubtipoMovimientoPersonal::CONTRATO_FINALIZADO);
});

test('si el reemplazo ya tiene su cesación en curso, no se prepara otra', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    $suplente = ($this->servidor)(TipoNombramiento::SERVICIOS_OCASIONALES, $ausencia);

    MovimientoPersonal::create([
        'servidor_id'        => $suplente->id,
        'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
        'subtipo_movimiento' => SubtipoMovimientoPersonal::MUTUO_ACUERDO->value,
        'estado'             => EstadoAccionPersonal::BORRADOR,
        'descripcion'        => 'Terminación por mutuo acuerdo',
        'fecha_efectiva'     => now()->addDays(5)->toDateString(),
    ]);

    ($this->registrar)(($this->reintegrar)($ausencia, $this->hoy)->json('datos.id'));

    expect(MovimientoPersonal::where('servidor_id', $suplente->id)->count())->toBe(1);
});

test('el que rige más tarde prepara la salida el día en que rige', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    $suplente = ($this->servidor)(TipoNombramiento::SERVICIOS_OCASIONALES, $ausencia);
    $regreso = now()->addDays(10)->toDateString();

    ($this->registrar)(($this->reintegrar)($ausencia, $regreso)->json('datos.id'));

    expect(MovimientoPersonal::where('servidor_id', $suplente->id)->exists())->toBeFalse();

    $this->estados->aplicarVigentes($regreso);

    expect(MovimientoPersonal::where('servidor_id', $suplente->id)->sole()->fecha_efectiva->toDateString())
        ->toBe(now()->addDays(9)->toDateString());
});

// ── Anular ──────────────────────────────────────────────────────

test('anular el reintegro anula la salida en borrador y la ausencia vuelve a su fin', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    $suplente = ($this->servidor)(TipoNombramiento::SERVICIOS_OCASIONALES, $ausencia);

    $reintegro = ($this->registrar)(($this->reintegrar)($ausencia, $this->hoy)->json('datos.id'));

    ($this->anular)($reintegro);

    $salida = MovimientoPersonal::where('servidor_id', $suplente->id)->sole();

    expect($salida->estado)->toBe(EstadoAccionPersonal::ANULADA)
        ->and($salida->motivo_anulacion)->toContain($reintegro->codigo_registro)
        ->and($ausencia->fresh()->finEfectivo()->toDateString())->toBe($this->fin);

    // Y se puede preparar otro.
    ($this->reintegrar)($ausencia, $this->hoy)->assertCreated();
});

test('si la salida ya se registró, se anula ella primero', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    $suplente = ($this->servidor)(TipoNombramiento::SERVICIOS_OCASIONALES, $ausencia);

    $reintegro = ($this->registrar)(($this->reintegrar)($ausencia, $this->hoy)->json('datos.id'));
    $salida = ($this->registrar)(MovimientoPersonal::where('servidor_id', $suplente->id)->sole()->id);

    expect(ContratoServidor::where('servidor_id', $suplente->id)->sole()->estado->value)->not->toBe('vigente');

    expect(fn () => ($this->anular)($reintegro))
        ->toThrow(ReglaNegocioException::class, "ya cesó con {$salida->codigo_registro}");

    // Anulada la salida, el contrato del reemplazo vuelve, y el reintegro ya se anula.
    ($this->anular)($salida);
    ($this->anular)($reintegro);

    expect(ContratoServidor::where('servidor_id', $suplente->id)->sole()->estado->value)->toBe('vigente')
        ->and($reintegro->fresh()->estado)->toBe(EstadoAccionPersonal::ANULADA);
});

test('no se anula una ausencia con reemplazo vigente: se usa el reintegro', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    ($this->servidor)(TipoNombramiento::SERVICIOS_OCASIONALES, $ausencia);

    expect(fn () => ($this->anular)($ausencia))
        ->toThrow(ReglaNegocioException::class, 'use el reintegro');
});

test('ni una con el ingreso de su reemplazo en trámite', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());

    $suplente = Servidor::create([
        'cedula' => '8899999998', 'nombre' => 'Suplente', 'apellido' => 'En trámite',
        'regimen_laboral' => 'losep', 'fecha_ingreso_institucion' => $this->hoy,
    ]);

    MovimientoPersonal::create([
        'servidor_id'                 => $suplente->id,
        'tipo_movimiento'             => TipoMovimientoPersonal::INGRESO->value,
        'tipo_nombramiento_propuesto' => TipoNombramiento::SERVICIOS_OCASIONALES->value,
        'estado'                      => EstadoAccionPersonal::BORRADOR,
        'descripcion'                 => 'Reemplazo en trámite',
        'fecha_efectiva'              => $this->hoy,
        'cubre_movimiento_id'         => $ausencia->id,
    ]);

    expect(fn () => ($this->anular)($ausencia))
        ->toThrow(ReglaNegocioException::class, 'ingreso de reemplazo en trámite');
});

test('ni una ausencia con su reintegro', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());
    ($this->reintegrar)($ausencia, $this->hoy)->assertCreated();

    expect(fn () => ($this->anular)($ausencia))
        ->toThrow(ReglaNegocioException::class, 'anule antes el reintegro');
});

test('una ausencia sin reemplazo ni reintegro se sigue anulando', function () {
    $ausencia = ($this->ausencia)(($this->servidor)());

    ($this->anular)($ausencia);

    expect($ausencia->fresh()->estado)->toBe(EstadoAccionPersonal::ANULADA);
});

// ── El fin natural ──────────────────────────────────────────────

test('el comando diario prepara el reintegro de las ausencias que terminaron', function () {
    $finHace3 = now()->subDays(3);
    $terminada = ($this->ausencia)(($this->servidor)(), now()->subYear()->toDateString(), $finHace3->toDateString());

    // Terminó hace dos meses: de antes de que el reintegro existiera.
    $antigua = ($this->ausencia)(($this->servidor)(), now()->subYears(2)->toDateString(), now()->subMonths(2)->toDateString());

    // Quien ya no tiene vínculo no tiene a qué volver.
    $sinVinculo = ($this->servidor)();
    ContratoServidor::where('servidor_id', $sinVinculo->id)->update(['estado' => 'terminado']);
    $deUnCesado = ($this->ausencia)($sinVinculo, now()->subYear()->toDateString(), $finHace3->toDateString());

    $this->artisan('sgth:acciones:aplicar-vigentes')->assertSuccessful();

    $reintegro = $terminada->fresh()->reintegro;

    expect($reintegro)->not->toBeNull()
        ->and($reintegro->estado)->toBe(EstadoAccionPersonal::BORRADOR)
        ->and($reintegro->fecha_efectiva->toDateString())->toBe($finHace3->copy()->addDay()->toDateString())
        ->and($reintegro->descripcion)->toContain($terminada->codigo_registro)
        ->and($antigua->fresh()->reintegro)->toBeNull()
        ->and($deUnCesado->fresh()->reintegro)->toBeNull();

    // Al día siguiente no prepara otro.
    $this->artisan('sgth:acciones:aplicar-vigentes')->assertSuccessful();

    expect($terminada->reintegros()->count())->toBe(1);
});

test('la que ya tiene reintegro no sale entre las que vencen', function () {
    $titular = ($this->servidor)();
    $ausencia = ($this->ausencia)($titular, fin: now()->addDays(10)->toDateString());

    $this->artisan('sgth:acciones:aplicar-vigentes')
        ->expectsOutputToContain($titular->apellido)
        ->assertSuccessful();

    ($this->reintegrar)($ausencia, now()->addDays(5)->toDateString())->assertCreated();

    $this->artisan('sgth:acciones:aplicar-vigentes')
        ->doesntExpectOutputToContain($titular->apellido)
        ->assertSuccessful();
});

// ── El documento ────────────────────────────────────────────────

test('el documento dice qué ausencia termina y cuál fue su último día', function () {
    $ausencia = ($this->ausencia)(($this->servidor)(), '2026-03-01', '2026-08-31');
    $reintegro = MovimientoPersonal::findOrFail(($this->reintegrar)($ausencia, '2026-07-15')->json('datos.id'));

    $firma = fn (RolFirmaAccionPersonal $rol) => ['rotulo' => $rol->rotuloDocumento(), 'nombre' => null, 'cargo' => null];

    $html = view('pdf.expediente.accion-personal', [
        'movimiento'         => $reintegro,
        'servidor'           => $reintegro->servidor,
        'firmaAutoridad'     => $firma(RolFirmaAccionPersonal::AUTORIDAD_NOMINADORA),
        'firmaTalentoHumano' => $firma(RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO),
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Reintegro')
        ->and($html)->toContain($ausencia->codigo_registro)
        ->and($html)->toContain('del 01/03/2026 al 31/08/2026')
        ->and($html)->toContain('El último día de la ausencia es el')
        ->and($html)->toContain('14/07/2026');
});
