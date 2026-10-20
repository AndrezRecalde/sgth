<?php

namespace Tests\Feature\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\EstadoAccionPersonal;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Mail\Expediente\LiquidacionObreroMail;
use App\Models\Dispensario\FichaSaludOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\TerminacionObreroService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.5 del diseño de Acciones de Personal (9.15): la terminación del
| contrato de un obrero, con sus causales del Código del Trabajo, la
| liquidación referencial de lo que el Código fija y el acta de finiquito.
| Hasta aquí solo se le terminaba por visto bueno.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();

    $this->travelTo(Carbon::parse('2026-06-15 12:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'OOPP-T', 'nombre' => 'Obras Públicas T', 'nivel' => 1]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10, 'regimen_laboral' => 'codigo_trabajo',
    ]);

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->contador = 0;

    // Un obrero con contrato desde el 1 de junio de 2021, que gana 620.
    $this->obrero = function (string $desde = '2021-06-01'): Servidor {
        $this->contador++;
        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8900000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Obrero',
            'apellido'        => 'Terminado'.$this->contador,
            'regimen_laboral' => 'codigo_trabajo',
        ]);
        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => TipoNombramiento::CODIGO_TRABAJO->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => $desde,
            'remuneracion'             => 620,
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->terminar = fn (Servidor $s, string $causal, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                    => 'cesacion',
            'causal'                   => $causal,
            'descripcion'              => 'Terminación del contrato de trabajo',
            'fecha_efectiva'           => '2026-06-15',
            'requiere_dictamen_medico' => false,
            ...$extra,
        ],
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── Las causales ────────────────────────────────────────────────

test('el obrero tiene sus causales del Código del Trabajo', function () {
    $causales = collect(ClaseAccionPersonal::CESACION->causales())
        ->filter(fn (SubtipoMovimientoPersonal $c) => $c->elegiblePara(TipoNombramiento::CODIGO_TRABAJO))
        ->map(fn (SubtipoMovimientoPersonal $c) => $c->value)
        ->values()
        ->all();

    expect($causales)->toBe([
        'desahucio', 'acuerdo_de_partes', 'conclusion_de_obra', 'prueba_no_superada_ct', 'despido_intempestivo',
        'jubilacion_ct', 'retiro_voluntario_ct', 'muerte_o_incapacidad', 'caso_fortuito',
    ])
        ->and(ClaseAccionPersonal::CESACION->elegiblePara(TipoNombramiento::CODIGO_TRABAJO))->toBeTrue()
        // Las de la LOSEP no le aplican.
        ->and(SubtipoMovimientoPersonal::RENUNCIA->elegiblePara(TipoNombramiento::CODIGO_TRABAJO))->toBeFalse();
});

test('la terminación cierra el vínculo con su hoja de registro interno', function () {
    $obrero = ($this->obrero)();

    $registrada = ($this->registrar)(($this->terminar)($obrero, 'conclusion_de_obra')->assertCreated()->json('datos.id'));

    expect($registrada->codigo_registro)->toBe('RI-2026-0001')
        ->and(ContratoServidor::where('servidor_id', $obrero->id)->first()->estado->value)->toBe('terminado');

    // Sin rubros que fije el Código: no le corresponde nada aquí.
    $this->getJson("/api/v1/expediente/movimientos/{$registrada->id}")
        ->assertOk()
        ->assertJsonPath('datos.lo_que_corresponde', null);
});

test('el desahucio pide la fecha de la renuncia, y avisa si rige antes de los 15 días', function () {
    $obrero = ($this->obrero)();

    ($this->terminar)($obrero, 'desahucio')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, '15 días de aviso'));

    ($this->terminar)($obrero, 'desahucio', ['fecha_presentacion_renuncia' => '2026-06-10'])
        ->assertCreated()
        ->assertJsonPath('datos.desahucio_sin_el_aviso', true);

    ($this->terminar)(($this->obrero)(), 'desahucio', ['fecha_presentacion_renuncia' => '2026-05-25'])
        ->assertCreated()
        ->assertJsonPath('datos.desahucio_sin_el_aviso', false);
});

test('no superar el período de prueba procede solo dentro de los 90 días', function () {
    ($this->terminar)(($this->obrero)('2026-05-01'), 'prueba_no_superada_ct')->assertCreated();

    ($this->terminar)(($this->obrero)(), 'prueba_no_superada_ct')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Art. 15'));
});

test('el despido intempestivo de una obrera en lactancia no procede: es ineficaz', function () {
    $obrera = ($this->obrero)();
    FichaSaludOcupacional::create([
        'servidor_id'      => $obrera->id,
        'evaluador_id'     => User::factory()->create()->id,
        'tipo_ficha'       => 'periodica',
        'fecha_evaluacion' => now()->subMonth()->toDateString(),
        'grupo_lactancia'  => true,
        'estado'           => true,
    ]);

    ($this->terminar)($obrera, 'despido_intempestivo')
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Art. 195.1'));
});

// ── Lo que corresponde ──────────────────────────────────────────

test('al desahucio le corresponde el 25 % de la remuneración por año de servicio, sin el valor', function () {
    $id = ($this->terminar)(($this->obrero)(), 'desahucio', ['fecha_presentacion_renuncia' => '2026-05-25'])->json('datos.id');

    // Del 1 de junio de 2021 al 15 de junio de 2026: 1841 días, 5,04 años.
    $corresponde = app(TerminacionObreroService::class)->loQueCorresponde(MovimientoPersonal::findOrFail($id));

    // La liquidación la calcula Financiero: el SGTH es informativo (2026-10-20).
    expect($corresponde['anios_servicio'])->toBe(5.04)
        ->and($corresponde['rubros'])->toHaveCount(1)
        ->and($corresponde['rubros'][0])->toBe([
            'concepto'   => 'Bonificación por desahucio',
            'base_legal' => 'Código del Trabajo, Art. 185',
            'detalle'    => 'El 25 % de la última remuneración por cada año de servicio',
        ])
        ->and($corresponde)->not->toHaveKey('total');
});

test('al despido intempestivo le corresponde también la indemnización, con su regla', function () {
    $id = ($this->terminar)(($this->obrero)(), 'despido_intempestivo')->assertCreated()->json('datos.id');

    $corresponde = app(TerminacionObreroService::class)->loQueCorresponde(MovimientoPersonal::findOrFail($id));

    expect(array_column($corresponde['rubros'], 'concepto'))->toBe(['Indemnización por despido intempestivo', 'Bonificación por desahucio'])
        ->and($corresponde['rubros'][0]['detalle'])->toContain('contando la fracción como año completo, hasta 25');
});

test('al registrarse, la liquidación sale por correo a Financiero', function () {
    Mail::fake();

    $financiera = UnidadAdministrativa::create(['codigo' => 'FIN-T', 'nombre' => 'Gestión Financiera T', 'nivel' => 1]);
    $financiera->forceFill(['es_unidad_financiera' => true])->save();
    $puestoJefe = Puesto::create(['unidad_administrativa_id' => $financiera->id, 'plazas' => 1, 'es_jefe' => true]);
    $jefe = Servidor::create(['cedula' => '0912345678', 'nombre' => 'Jefe', 'apellido' => 'Financiero', 'regimen_laboral' => 'losep']);
    ContratoServidor::create([
        'servidor_id' => $jefe->id, 'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $financiera->id, 'puesto_id' => $puestoJefe->id,
        'fecha_inicio' => '2018-01-01', 'estado' => 'vigente',
    ]);
    User::factory()->create(['servidor_id' => $jefe->id, 'email' => 'jefe.financiero@gadpe.gob.ec']);

    $id = ($this->terminar)(($this->obrero)(), 'acuerdo_de_partes')->json('datos.id');
    $url = "/api/v1/expediente/movimientos/{$id}/transicionar";

    $this->putJson($url, ['estado' => 'suscrita'])->assertOk()->assertJsonPath('meta', null);
    $this->putJson($url, ['estado' => 'registrada'])->assertOk()->assertJsonPath('meta.aviso_financiero', 'enviado');

    Mail::assertSent(LiquidacionObreroMail::class, fn (LiquidacionObreroMail $m) => $m->hasTo('jefe.financiero@gadpe.gob.ec')
        && ! $m->anulada
        && $m->liquidacion['rubros'][0]['concepto'] === 'Bonificación por desahucio'
        && ! array_key_exists('total', $m->liquidacion)
        && str_contains($m->envelope()->subject, 'para liquidar'));
});

test('la hoja de registro interno trae lo que corresponde, sin montos', function () {
    $registrada = ($this->registrar)(($this->terminar)(($this->obrero)(), 'despido_intempestivo')->json('datos.id'));

    $html = view('pdf.expediente.registro-interno', [
        'movimiento'         => $registrada->fresh(['unidadOrigen', 'puestoOrigen.cargo']),
        'descuento'          => null,
        'corresponde'        => app(TerminacionObreroService::class)->loQueCorresponde($registrada),
        'anulada'            => false,
        'servidor'           => $registrada->servidor,
        'firmaTalentoHumano' => ['rotulo' => RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO->rotuloDocumento(), 'nombre' => null, 'cargo' => 'Director/a de Talento Humano'],
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Lo que corresponde a la terminación')
        ->toContain('Código del Trabajo, Art. 188')
        ->toContain('Código del Trabajo, Arts. 185 y 188')
        ->toContain('5,04 años')
        ->not->toContain('Total referencial');
});

// ── El acta de finiquito ────────────────────────────────────────

test('el acta de finiquito se registra sobre la terminación, con su PDF', function () {
    Storage::fake('local');

    $obrero = ($this->obrero)();
    $terminacion = ($this->registrar)(($this->terminar)($obrero, 'conclusion_de_obra')->json('datos.id'));
    $ruta = "/api/v1/expediente/movimientos/{$terminacion->id}/acta-finiquito";

    $this->getJson('/api/v1/expediente/terminaciones-obreros?sin_acta=1')
        ->assertOk()
        ->assertJsonPath('datos.0.movimiento_id', $terminacion->id)
        ->assertJsonPath('datos.0.acta', null);

    // No antes de la terminación.
    $this->postJson($ruta, ['numero' => 'SUT-2026-0001', 'fecha' => '2026-06-01', 'valor_pagado' => 1200])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'posterior a la terminación'));

    $this->post($ruta, [
        'numero'       => 'SUT-2026-0001',
        'fecha'        => '2026-06-15',
        'valor_pagado' => 1200,
        'archivo'      => UploadedFile::fake()->create('acta.pdf', 120, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('datos.numero', 'SUT-2026-0001')
        ->assertJsonPath('datos.tiene_documento', true);

    $this->getJson('/api/v1/expediente/terminaciones-obreros?sin_acta=1')->assertOk()->assertJsonCount(0, 'datos');
    $this->get("{$ruta}/documento")->assertOk();
});

test('el acta es solo de la terminación de un obrero ya registrada', function () {
    $borrador = ($this->terminar)(($this->obrero)(), 'conclusion_de_obra')->json('datos.id');

    $this->postJson("/api/v1/expediente/movimientos/{$borrador}/acta-finiquito", [
        'numero' => 'SUT-2026-0002', 'fecha' => '2026-06-15', 'valor_pagado' => 0,
    ])->assertStatus(422)->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ya registrada'));
});
