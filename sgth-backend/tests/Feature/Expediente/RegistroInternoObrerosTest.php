<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\TipoNombramiento;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Cargo;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\ContratoServidorService;
use App\Services\Expediente\MovimientoPersonalStateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.3 del diseño de Acciones de Personal (9.15; TH N8, 14 y 15): a los
| obreros los rige el Código del Trabajo, no la LOSEP. Su contrato y sus
| adendas los firman el empleador y el trabajador, y cada acto deja una hoja
| de registro interno, RI-AAAA-NNNN, que firma Talento Humano.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->travelTo(Carbon::parse('2026-06-15 12:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'OOPP', 'nombre' => 'Dirección de Obras Públicas', 'nivel' => 1]);
    $this->otraUnidad = UnidadAdministrativa::create(['codigo' => 'MANT', 'nombre' => 'Mantenimiento Vial', 'nivel' => 1]);

    $this->partida = PartidaPresupuestaria::create([
        'codigo' => '510106', 'descripcion' => 'Salarios unificados',
        'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
    ]);

    $cargo = fn (string $nombre) => Cargo::create(['nombre' => $nombre, 'activo' => true]);
    $chofer = $cargo('Chofer de vehículo pesado');
    $operador = $cargo('Operador de maquinaria');

    $puesto = fn (UnidadAdministrativa $u, Cargo $c, string $regimen = 'codigo_trabajo', int $plazas = 2) => Puesto::create([
        'unidad_administrativa_id'  => $u->id,
        'cargo_id'                  => $c->id,
        'plazas'                    => $plazas,
        'regimen_laboral'           => $regimen,
        'partida_presupuestaria_id' => $this->partida->id,
        'activo'                    => true,
    ]);

    $this->chofer = $puesto($this->unidad, $chofer);
    $this->choferEnOtra = $puesto($this->otraUnidad, $chofer);
    $this->operador = $puesto($this->unidad, $operador);
    $this->deLaLosep = $puesto($this->unidad, $cargo('Analista'), 'losep');

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->contador = 0;

    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula'          => str_pad((string) (8800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Obrero',
            'apellido'        => 'Prueba'.$this->contador,
            'regimen_laboral' => 'codigo_trabajo',
        ]);
    };

    // Un chofer con contrato de trabajo desde 2021, en el Edificio Central.
    $this->obrero = function (): Servidor {
        $servidor = ($this->persona)();

        app(ContratoServidorService::class)->crear($servidor->id, [
            'tipo_nombramiento'         => TipoNombramiento::CODIGO_TRABAJO->value,
            'numero_contrato'           => 'CT-2021-0'.$this->contador,
            'unidad_administrativa_id'  => $this->unidad->id,
            'puesto_id'                 => $this->chofer->id,
            'lugar_trabajo'             => 'Edificio Central',
            'fecha_inicio'              => '2021-06-01',
            'remuneracion'              => 620,
            'partida_presupuestaria_id' => $this->partida->id,
            'estado'                    => 'vigente',
        ]);

        return $servidor->fresh();
    };

    $this->accion = fn (Servidor $s, array $datos) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        ['descripcion' => 'Acto del contrato de trabajo', 'fecha_efectiva' => '2026-06-15', ...$datos],
    );

    $this->ocupacion = fn (Servidor $s, array $extra = []) => ($this->accion)($s, [
        'clase'                => 'cambio_ocupacion',
        'unidad_destino_id'    => $this->unidad->id,
        'puesto_destino_id'    => $this->operador->id,
        'fecha_consentimiento' => '2026-06-10',
        'resolucion_numero'    => 'ADENDA-'.$this->contador,
        ...$extra,
    ]);

    $this->registrar = function (int $id, array $alSuscribir = []): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA, $alSuscribir);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── El instrumento y la hoja ────────────────────────────────────

test('el ingreso de un obrero es del Código del Trabajo, con su hoja RI y sin acción de personal', function () {
    $id = ($this->accion)(($this->persona)(), [
        'clase'                       => 'ingreso',
        'tipo_nombramiento_propuesto' => 'codigo_trabajo',
        'unidad_destino_id'           => $this->unidad->id,
        'puesto_destino_id'           => $this->operador->id,
        'numero_contrato'             => 'CT-2026-0101',
        'remuneracion_propuesta'      => 600,
        'lugar_trabajo'               => 'San Mateo',
        'requiere_dictamen_medico'    => false,
    ])
        ->assertCreated()
        ->assertJsonPath('datos.instrumento', 'documento_ct')
        ->assertJsonPath('datos.instrumento_etiqueta', 'Registro interno (Código del Trabajo)')
        ->assertJsonPath('datos.tiene_documento_imprimible', true)
        ->json('datos.id');

    $registrado = ($this->registrar)($id);
    $contrato = ContratoServidor::where('movimiento_origen_id', $id)->firstOrFail();

    expect($registrado->codigo_registro)->toBe('RI-2026-0001')
        // La hoja la firma solo Talento Humano.
        ->and($registrado->fecha_suscripcion)->not->toBeNull()
        ->and($registrado->firmante_autoridad_nombre)->toBeNull()
        // El número del contrato y el lugar quedan en el vínculo.
        ->and($contrato->numero_contrato)->toBe('CT-2026-0101')
        ->and($contrato->lugar_trabajo)->toBe('San Mateo');

    expect(fn () => $this->estados->transicionar($registrado, EstadoAccionPersonal::NOTIFICADA))
        ->toThrow(ReglaNegocioException::class, 'no se notifica');
});

test('la ficha médica sigue siendo obligatoria al ingreso de un obrero', function () {
    ($this->accion)(($this->persona)(), [
        'clase'                       => 'ingreso',
        'tipo_nombramiento_propuesto' => 'codigo_trabajo',
        'unidad_destino_id'           => $this->unidad->id,
        'puesto_destino_id'           => $this->operador->id,
    ])->assertCreated()->assertJsonPath('datos.requiere_dictamen_medico', true);
});

test('los demás actos del obrero también van con la hoja de registro interno', function () {
    ($this->accion)(($this->obrero)(), [
        'clase'       => 'licencia_sin_remuneracion',
        'causal'      => 'segun_su_regimen',
        'fecha_inicio' => '2026-07-01',
        'fecha_fin'   => '2026-07-10',
    ])->assertCreated()->assertJsonPath('datos.instrumento', 'documento_ct');
});

test('el catálogo dice qué cambia cada acto del obrero', function () {
    $clases = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk()->json('datos.clases'))
        ->keyBy('codigo');

    expect($clases['cambio_ocupacion']['cambio_del_obrero'])->toBe('ocupacion')
        ->and($clases['cambio_lugar']['cambio_del_obrero'])->toBe('lugar')
        ->and($clases['cambio_lugar']['nombramientos_elegibles'])->toBe(['codigo_trabajo'])
        ->and($clases['incremento_remuneracion']['cambio_del_obrero'])->toBe('remuneracion')
        ->and($clases['ingreso']['documento_ct_para'])->toBe(['codigo_trabajo'])
        ->and($clases['licencia_sin_remuneracion']['documento_ct_para'])->toBe(['codigo_trabajo'])
        // Y su terminación, desde que tiene sus causales (fase 4.5).
        ->and($clases['cesacion']['documento_ct_para'])->toBe(['codigo_trabajo'])
        ->and($clases['traslado']['documento_ct_para'])->toBe([])
        ->and($clases['traslado']['cambio_del_obrero'])->toBeNull();
});

// ── El cambio de ocupación ──────────────────────────────────────

test('el cambio de ocupación mueve el vínculo, y anularlo lo devuelve', function () {
    $obrero = ($this->obrero)();

    $id = ($this->ocupacion)($obrero, ['remuneracion_propuesta' => 650])
        ->assertCreated()
        ->assertJsonPath('datos.instrumento', 'documento_ct')
        ->assertJsonPath('datos.propone_situacion', true)
        ->json('datos.id');

    $registrado = ($this->registrar)($id);
    $contrato = $obrero->fresh()->contratoVigente;

    expect($registrado->codigo_registro)->toBe('RI-2026-0001')
        ->and($registrado->fecha_consentimiento->toDateString())->toBe('2026-06-10')
        ->and($contrato->puesto_id)->toBe($this->operador->id)
        ->and((float) $contrato->remuneracion)->toBe(650.0)
        ->and($obrero->fresh()->puesto_id)->toBe($this->operador->id);

    $this->estados->transicionar($registrado, EstadoAccionPersonal::ANULADA, [
        'motivo_anulacion' => 'La adenda no llegó a firmarse.',
    ]);

    $contrato = $obrero->fresh()->contratoVigente;

    expect($contrato->puesto_id)->toBe($this->chofer->id)
        ->and((float) $contrato->remuneracion)->toBe(620.0);
});

test('sin el consentimiento escrito del trabajador no hay cambio de ocupación', function () {
    ($this->ocupacion)(($this->obrero)(), ['fecha_consentimiento' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'consentimiento') && str_contains($m, 'Art. 192'));
});

test('la ocupación nueva es otro puesto del catálogo de obreros, y la remuneración no baja', function () {
    $obrero = ($this->obrero)();

    ($this->ocupacion)($obrero, ['remuneracion_propuesta' => 500])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'nunca baja'));

    ($this->ocupacion)($obrero, ['puesto_destino_id' => $this->deLaLosep->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'catálogo de obreros'));

    ($this->ocupacion)($obrero, ['unidad_destino_id' => $this->otraUnidad->id, 'puesto_destino_id' => $this->choferEnOtra->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'misma denominación') && str_contains($m, 'cambio de lugar'));
});

test('sin el número de la adenda no se suscribe', function () {
    $id = ($this->ocupacion)(($this->obrero)(), ['resolucion_numero' => null])->assertCreated()->json('datos.id');

    expect(fn () => $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA))
        ->toThrow(ReglaNegocioException::class, 'Adenda N.º');
});

// ── El cambio de lugar ──────────────────────────────────────────

test('el cambio de lugar mueve el lugar de trabajo sin tocar la ocupación, y anularlo lo devuelve', function () {
    $obrero = ($this->obrero)();

    $id = ($this->accion)($obrero, ['clase' => 'cambio_lugar', 'lugar_trabajo' => 'San Mateo', 'resolucion_numero' => 'ADENDA-2'])
        ->assertCreated()
        ->json('datos.id');

    $cambio = MovimientoPersonal::findOrFail($id);

    expect($cambio->lugar_trabajo_origen)->toBe('Edificio Central')
        ->and($cambio->puesto_destino_id)->toBeNull()
        ->and($cambio->remuneracion_propuesta)->toBeNull();

    $registrado = ($this->registrar)($id);

    expect($obrero->fresh()->contratoVigente->lugar_trabajo)->toBe('San Mateo')
        ->and($obrero->fresh()->contratoVigente->puesto_id)->toBe($this->chofer->id);

    $this->estados->transicionar($registrado, EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Se registró por error.']);

    expect($obrero->fresh()->contratoVigente->lugar_trabajo)->toBe('Edificio Central');
});

test('el cambio de lugar a otra unidad es en un puesto de la misma denominación', function () {
    $obrero = ($this->obrero)();

    ($this->registrar)(($this->accion)($obrero, [
        'clase'             => 'cambio_lugar',
        'unidad_destino_id' => $this->otraUnidad->id,
        'puesto_destino_id' => $this->choferEnOtra->id,
        'resolucion_numero' => 'ADENDA-3',
    ])->assertCreated()->json('datos.id'));

    expect($obrero->fresh()->contratoVigente->unidad_administrativa_id)->toBe($this->otraUnidad->id);

    ($this->accion)(($this->obrero)(), ['clase' => 'cambio_lugar', 'puesto_destino_id' => $this->operador->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'otra denominación'));

    ($this->accion)(($this->obrero)(), ['clase' => 'cambio_lugar', 'lugar_trabajo' => 'Edificio Central'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no cambia nada'));
});

// ── El incremento ───────────────────────────────────────────────

test('el incremento sube la remuneración, y nunca la deja igual ni la baja', function () {
    $obrero = ($this->obrero)();
    $incremento = fn (float $monto) => ($this->accion)($obrero, [
        'clase' => 'incremento_remuneracion', 'remuneracion_propuesta' => $monto, 'resolucion_numero' => 'ADENDA-4',
    ]);

    $incremento(620)->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'mayor que la actual'));
    $incremento(600)->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'nunca baja'));

    $registrado = ($this->registrar)($incremento(700)->assertCreated()->json('datos.id'), [
        'dictamen_presupuestario_ref' => 'DF-2026-077',
    ]);

    expect((float) $obrero->fresh()->contratoVigente->remuneracion)->toBe(700.0);

    $this->estados->transicionar($registrado, EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Monto mal calculado.']);

    expect((float) $obrero->fresh()->contratoVigente->remuneracion)->toBe(620.0);
});

test('los de antes, con acción de personal, no tocaban el vínculo y siguen sin tocarlo', function () {
    foreach (['cambio_denominacion', 'incremento_remuneracion'] as $tipo) {
        $antes = new MovimientoPersonal(['tipo_movimiento' => $tipo, 'instrumento' => InstrumentoActo::ACCION_PERSONAL->value]);
        $ahora = new MovimientoPersonal(['tipo_movimiento' => $tipo, 'instrumento' => InstrumentoActo::DOCUMENTO_CT->value]);

        expect($antes->tocaElVinculo())->toBeFalse($tipo)
            ->and($ahora->tocaElVinculo())->toBeTrue($tipo);
    }
});

// ── La hoja impresa ─────────────────────────────────────────────

test('la hoja de registro interno enseña lo de antes, lo nuevo y lo que no cambia', function () {
    $obrero = ($this->obrero)();
    $registrado = ($this->registrar)(($this->accion)($obrero, [
        'clase' => 'cambio_lugar', 'lugar_trabajo' => 'San Mateo', 'resolucion_numero' => 'ADENDA-5',
    ])->json('datos.id'));

    $this->get("/api/v1/expediente/movimientos/{$registrado->id}/accion-personal-pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $html = view('pdf.expediente.registro-interno', [
        'movimiento'         => $registrado->fresh(['unidadOrigen', 'puestoOrigen.cargo', 'unidadDestino', 'puestoDestino.cargo']),
        'descuento'          => null,
        'anulada'            => false,
        'servidor'           => $obrero,
        'firmaTalentoHumano' => ['rotulo' => RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO->rotuloDocumento(), 'nombre' => null, 'cargo' => 'Director/a de Talento Humano'],
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Hoja de registro interno')
        ->toContain($registrado->codigo_registro)
        ->toContain('Edificio Central')
        ->toContain('San Mateo')
        ->toContain('Sin cambio')
        ->toContain('ADENDA-5')
        ->toContain('Código del Trabajo, Art. 192');
});
