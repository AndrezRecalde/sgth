<?php

namespace Tests\Feature\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\AccionPersonalPdfService;
use App\Services\Expediente\ContratoServidorService;
use App\Services\Expediente\ContratoVencidoService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.2 del diseño de Acciones de Personal (9.10; TH N6 y 25): quien tiene
| Servicios Profesionales no es servidor ni tiene relación de dependencia
| (Reglamento a la LOSEP, Art. 148). Su contrato, sus adendas y su terminación
| los firman las partes, y aquí se registran sin acción de personal.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    // A mediados de año, para que el año calendario del contrato sea fijo.
    $this->travelTo(Carbon::parse('2026-06-15 12:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-CC', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);
    $this->otraUnidad = UnidadAdministrativa::create([
        'codigo' => 'OBR-CC', 'nombre' => 'Dirección de Obras Públicas', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create(['codigo' => 'P-CC1', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 1]);
    $this->otroPuesto = Puesto::create(['codigo' => 'P-CC2', 'unidad_administrativa_id' => $this->otraUnidad->id, 'plazas' => 1]);

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->contador = 0;

    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8700000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Profesional',
            'apellido'        => 'Civil'.$this->contador,
            'regimen_laboral' => 'servicios_profesionales',
        ]);
    };

    // Un contrato civil vigente desde el 1 de febrero: vence el 31 de diciembre.
    $this->contratado = function (array $extra = []): Servidor {
        $servidor = ($this->persona)();

        app(ContratoServidorService::class)->crear($servidor->id, [
            'tipo_nombramiento'        => TipoNombramiento::SERVICIOS_PROFESIONALES->value,
            'numero_contrato'          => 'CSP-2026-0'.$this->contador,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2026-02-01',
            'remuneracion'             => 1500,
            'estado'                   => 'vigente',
            ...$extra,
        ]);

        return $servidor->fresh();
    };

    $this->accion = fn (Servidor $s, array $datos) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        ['descripcion' => 'Acto del contrato civil', 'fecha_efectiva' => '2026-06-15', ...$datos],
    );

    $this->ingreso = fn (Servidor $s, array $extra = []) => ($this->accion)($s, [
        'clase'                       => 'ingreso',
        'tipo_nombramiento_propuesto' => 'servicios_profesionales',
        'unidad_destino_id'           => $this->unidad->id,
        'puesto_destino_id'           => $this->puesto->id,
        'numero_contrato'             => 'CSP-2026-01'.$this->contador,
        'remuneracion_propuesta'      => 1500,
        ...$extra,
    ]);

    $this->adenda = fn (Servidor $s, array $extra = []) => ($this->accion)($s, [
        'clase'             => 'adenda',
        'resolucion_numero' => 'ADENDA-'.$this->contador,
        ...$extra,
    ]);

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── El instrumento ──────────────────────────────────────────────

test('el ingreso con contrato de Servicios Profesionales es del contrato civil', function () {
    ($this->ingreso)(($this->persona)())
        ->assertCreated()
        ->assertJsonPath('datos.instrumento', 'contrato_civil')
        ->assertJsonPath('datos.instrumento_etiqueta', 'Contrato civil')
        ->assertJsonPath('datos.tiene_documento_imprimible', false)
        // La ficha médica es opcional [TH 25]: abre desmarcada.
        ->assertJsonPath('datos.requiere_dictamen_medico', false);
});

test('el contrato se registra con su número, sin firmantes ni notificación', function () {
    $id = ($this->ingreso)(($this->persona)(), ['numero_contrato' => 'CSP-2026-0102'])->json('datos.id');

    $registrado = ($this->registrar)($id);

    expect($registrado->codigo_registro)->toBe('CSP-2026-0102')
        ->and($registrado->firmante_autoridad_nombre)->toBeNull()
        ->and($registrado->firmante_th_nombre)->toBeNull()
        ->and(ContratoServidor::where('movimiento_origen_id', $id)->first()->tipo_nombramiento)
        ->toBe(TipoNombramiento::SERVICIOS_PROFESIONALES)
        ->and($this->estados->destinosPara($registrado, $this->director))
        ->not->toContain(EstadoAccionPersonal::NOTIFICADA);

    expect(fn () => $this->estados->transicionar($registrado, EstadoAccionPersonal::NOTIFICADA))
        ->toThrow(ReglaNegocioException::class, 'no se notifica');
});

test('un número de contrato ya registrado se rechaza con el nombre de quien lo tiene', function () {
    ($this->registrar)(($this->ingreso)(($this->persona)(), ['numero_contrato' => 'CSP-2026-0103'])->json('datos.id'));

    $repetido = ($this->ingreso)(($this->persona)(), ['numero_contrato' => 'CSP-2026-0103'])->json('datos.id');

    expect(fn () => ($this->registrar)($repetido))
        ->toThrow(ReglaNegocioException::class, 'ya está registrado, a nombre de Profesional Civil1');
});

test('la terminación por vencimiento también es del contrato civil, y no pide documento', function () {
    $servidor = ($this->contratado)(['fecha_inicio' => '2025-03-01']);

    $resultado = app(ContratoVencidoService::class)->generarCesacionesPendientes('2026-06-15');
    $cesacion = MovimientoPersonal::findOrFail($resultado['generadas'][0]['movimiento_id']);

    expect($cesacion->instrumento)->toBe(InstrumentoActo::CONTRATO_CIVIL)
        ->and($cesacion->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::CONTRATO_FINALIZADO)
        ->and($cesacion->requiere_dictamen_medico)->toBeFalse();

    // El plazo terminó: no hay acta que pedir.
    $registrada = ($this->registrar)($cesacion->id);

    expect($registrada->codigo_registro)->toBe('CC-2026-0001')
        ->and(ContratoServidor::where('servidor_id', $servidor->id)->first()->estado->value)->toBe('terminado');
});

test('no tiene documento de acción de personal', function () {
    $id = ($this->adenda)(($this->contratado)(), ['remuneracion_propuesta' => 1800])->json('datos.id');
    ($this->registrar)($id);

    expect(fn () => app(AccionPersonalPdfService::class)->generarContent($id))
        ->toThrow(ReglaNegocioException::class, 'contrato civil no tiene documento');
});

// ── La adenda ───────────────────────────────────────────────────

test('la adenda es solo del contrato civil, y el traslado ya no lo es', function () {
    expect(ClaseAccionPersonal::ADENDA->nombramientosElegibles())->toBe([TipoNombramiento::SERVICIOS_PROFESIONALES])
        ->and(ClaseAccionPersonal::TRASLADO->elegiblePara(TipoNombramiento::SERVICIOS_PROFESIONALES))->toBeFalse();

    $catalogo = $this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk();
    $clases = collect($catalogo->json('datos.clases'));

    expect($clases->firstWhere('codigo', 'adenda'))
        ->toMatchArray(['pide_adenda' => true, 'pide_situacion_propuesta' => false, 'contrato_civil_para' => ['servicios_profesionales']])
        ->and($clases->firstWhere('codigo', 'ingreso')['contrato_civil_para'])->toBe(['servicios_profesionales'])
        ->and($clases->firstWhere('codigo', 'cesacion')['contrato_civil_para'])->toBe(['servicios_profesionales'])
        ->and($clases->firstWhere('codigo', 'traslado')['contrato_civil_para'])->toBe([]);

    ($this->accion)(($this->contratado)(), [
        'clase' => 'traslado', 'unidad_destino_id' => $this->otraUnidad->id, 'puesto_destino_id' => $this->otroPuesto->id,
    ])->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no aplica'));
});

test('la adenda cambia la unidad y el puesto, el plazo y los honorarios', function () {
    $servidor = ($this->contratado)();

    $id = ($this->adenda)($servidor, [
        'unidad_destino_id'      => $this->otraUnidad->id,
        'puesto_destino_id'      => $this->otroPuesto->id,
        'remuneracion_propuesta' => 1800,
        'fecha_fin_propuesta'    => '2026-11-30',
    ])
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'adenda')
        ->assertJsonPath('datos.instrumento', 'contrato_civil')
        ->assertJsonPath('datos.propone_situacion', true)
        ->json('datos.id');

    $registrada = ($this->registrar)($id);
    $contrato = $servidor->fresh()->contratoVigente;

    expect($registrada->codigo_registro)->toBe('CC-2026-0001')
        ->and($registrada->firmante_autoridad_nombre)->toBeNull()
        ->and($contrato->puesto_id)->toBe($this->otroPuesto->id)
        ->and($contrato->unidad_administrativa_id)->toBe($this->otraUnidad->id)
        ->and((float) $contrato->remuneracion)->toBe(1800.0)
        ->and($contrato->fecha_fin->toDateString())->toBe('2026-11-30')
        // El contrato sigue siendo el mismo, con su número y su inicio.
        ->and($contrato->numero_contrato)->toBe('CSP-2026-01')
        ->and($contrato->fecha_inicio->toDateString())->toBe('2026-02-01')
        ->and($servidor->fresh()->puesto_id)->toBe($this->otroPuesto->id);
});

test('lo que la adenda no cambia queda vacío, y no lo toca', function () {
    $servidor = ($this->contratado)();

    $id = ($this->adenda)($servidor, [
        'unidad_destino_id'      => $this->unidad->id,
        'puesto_destino_id'      => $this->puesto->id,
        'remuneracion_propuesta' => 1500,
        'fecha_fin_propuesta'    => '2026-09-30',
    ])->assertCreated()->json('datos.id');

    $adenda = MovimientoPersonal::findOrFail($id);

    expect($adenda->puesto_destino_id)->toBeNull()
        ->and($adenda->unidad_destino_id)->toBeNull()
        ->and($adenda->remuneracion_propuesta)->toBeNull()
        ->and($adenda->fecha_fin_propuesta->toDateString())->toBe('2026-09-30')
        ->and($adenda->fecha_fin_origen->toDateString())->toBe('2026-12-31');

    ($this->registrar)($id);
    $contrato = $servidor->fresh()->contratoVigente;

    expect($contrato->puesto_id)->toBe($this->puesto->id)
        ->and((float) $contrato->remuneracion)->toBe(1500.0)
        ->and($contrato->fecha_fin->toDateString())->toBe('2026-09-30');
});

test('una adenda que no cambia nada no se prepara', function () {
    ($this->adenda)(($this->contratado)(), ['remuneracion_propuesta' => 1500])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no cambia nada'));
});

test('el plazo no sale del año calendario del contrato, ni termina antes de que rija la adenda', function () {
    $servidor = ($this->contratado)();

    ($this->adenda)($servidor, ['fecha_fin_propuesta' => '2027-03-31'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'año calendario') && str_contains($m, '31/12/2026'));

    ($this->adenda)($servidor, ['fecha_fin_propuesta' => '2026-06-01'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'antes de que rija'));
});

test('el puesto es de la unidad indicada, y la unidad sola no dice a qué puesto', function () {
    $servidor = ($this->contratado)();

    ($this->adenda)($servidor, ['unidad_destino_id' => $this->otraUnidad->id, 'puesto_destino_id' => $this->puesto->id])
        ->assertStatus(422);

    ($this->adenda)($servidor, ['unidad_destino_id' => $this->otraUnidad->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Indique el puesto'));

    $this->otroPuesto->update(['activo' => false]);

    ($this->adenda)($servidor, ['unidad_destino_id' => $this->otraUnidad->id, 'puesto_destino_id' => $this->otroPuesto->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'inactivo'));
});

test('sin el número de la adenda firmada no se suscribe', function () {
    $id = ($this->adenda)(($this->contratado)(), ['remuneracion_propuesta' => 1800, 'resolucion_numero' => null])
        ->assertCreated()
        ->json('datos.id');

    expect(fn () => $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class, 'documento firmado');
});

test('corregir el borrador de la adenda la vuelve a comprobar', function () {
    $id = ($this->adenda)(($this->contratado)(), ['remuneracion_propuesta' => 1800])->json('datos.id');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['fecha_fin_propuesta' => '2027-01-31'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'año calendario'));

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['remuneracion_propuesta' => 1500])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no cambia nada'));

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['remuneracion_propuesta' => 1650])->assertOk();

    expect((float) MovimientoPersonal::findOrFail($id)->remuneracion_propuesta)->toBe(1650.0);
});

test('anular la adenda devuelve el contrato a como estaba', function () {
    $servidor = ($this->contratado)();

    $id = ($this->adenda)($servidor, [
        'unidad_destino_id'      => $this->otraUnidad->id,
        'puesto_destino_id'      => $this->otroPuesto->id,
        'remuneracion_propuesta' => 1800,
        'fecha_fin_propuesta'    => '2026-11-30',
    ])->json('datos.id');

    $registrada = ($this->registrar)($id);

    $this->estados->transicionar($registrada, EstadoAccionPersonal::ANULADA, [
        'motivo_anulacion' => 'La adenda se registró sobre el contrato equivocado.',
    ]);

    $contrato = $servidor->fresh()->contratoVigente;

    expect($contrato->puesto_id)->toBe($this->puesto->id)
        ->and($contrato->unidad_administrativa_id)->toBe($this->unidad->id)
        ->and((float) $contrato->remuneracion)->toBe(1500.0)
        ->and($contrato->fecha_fin->toDateString())->toBe('2026-12-31')
        ->and($servidor->fresh()->puesto_id)->toBe($this->puesto->id);
});

test('la adenda que rige más tarde cambia el contrato en su fecha', function () {
    $servidor = ($this->contratado)();

    $id = ($this->adenda)($servidor, ['fecha_efectiva' => '2026-07-01', 'remuneracion_propuesta' => 1700])->json('datos.id');
    ($this->registrar)($id);

    expect((float) $servidor->fresh()->contratoVigente->remuneracion)->toBe(1500.0);

    $this->travelTo(Carbon::parse('2026-07-01 08:00'));
    $resultado = $this->estados->aplicarVigentes('2026-07-01');

    expect(collect($resultado['aplicadas'])->pluck('id')->all())->toBe([$id])
        ->and((float) $servidor->fresh()->contratoVigente->remuneracion)->toBe(1700.0);
});

test('la cesación del vencimiento en trámite detiene la adenda que mueve el plazo', function () {
    $servidor = ($this->contratado)(['fecha_fin' => '2026-05-31']);
    app(ContratoVencidoService::class)->generarCesacionesPendientes('2026-06-15');

    $id = ($this->adenda)($servidor, ['fecha_fin_propuesta' => '2026-12-31'])->assertCreated()->json('datos.id');

    expect(fn () => ($this->registrar)($id))
        ->toThrow(ReglaNegocioException::class, 'Anúlela antes de cambiar el plazo con una adenda');
});

// ── La terminación ──────────────────────────────────────────────

test('el contrato civil termina por sus propias causales', function () {
    $causales = collect(ClaseAccionPersonal::CESACION->causales())
        ->filter(fn (SubtipoMovimientoPersonal $c) => $c->elegiblePara(TipoNombramiento::SERVICIOS_PROFESIONALES))
        ->map(fn (SubtipoMovimientoPersonal $c) => $c->value)
        ->values()
        ->all();

    expect($causales)->toBe(['fallecimiento', 'contrato_finalizado', 'terminacion_mutuo_acuerdo', 'terminacion_anticipada'])
        ->and(SubtipoMovimientoPersonal::TERMINACION_MUTUO_ACUERDO->baseLegal())->toBe('Código Civil Art. 1561');
});

test('la terminación por mutuo acuerdo cierra el contrato con su acta', function () {
    $servidor = ($this->contratado)();

    $id = ($this->accion)($servidor, [
        'clase' => 'cesacion', 'causal' => 'terminacion_mutuo_acuerdo', 'resolucion_numero' => 'ACTA-TERM-01',
    ])
        ->assertCreated()
        ->assertJsonPath('datos.instrumento', 'contrato_civil')
        ->json('datos.id');

    $registrada = ($this->registrar)($id);
    $contrato = ContratoServidor::where('servidor_id', $servidor->id)->firstOrFail();

    expect($registrada->codigo_registro)->toBe('CC-2026-0001')
        ->and($contrato->estado->value)->toBe('terminado')
        ->and($contrato->fecha_fin->toDateString())->toBe('2026-06-15')
        ->and($servidor->fresh()->estado)->toBeFalse();
});

test('la terminación anticipada sin el acta no se suscribe', function () {
    $id = ($this->accion)(($this->contratado)(), ['clase' => 'cesacion', 'causal' => 'terminacion_anticipada'])
        ->assertCreated()
        ->json('datos.id');

    expect(fn () => $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class, 'documento firmado');
});

test('las causales de la LOSEP no le aplican', function () {
    ($this->accion)(($this->contratado)(), ['clase' => 'cesacion', 'causal' => 'renuncia'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no aplica'));
});
