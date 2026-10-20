<?php

namespace Tests\Feature\Expediente;

use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\RolFirmaAccionPersonal;
use App\Enums\SubtipoMovimientoPersonal;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoNombramiento;
use App\Mail\Expediente\LiquidacionObreroMail;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ActaFiniquito;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\TerminacionObreroService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 4.5b del diseño de Acciones de Personal (9.15): el reintegro del obrero
| cuyo despido el juez declaró ineficaz (Código del Trabajo, Arts. 195.1 a
| 195.3). Es la restitución de la fase 2.6 con las reglas del Código: basta la
| providencia que ordena el reintegro inmediato, vuelve con su contrato y su
| remuneración, y se pagan las remuneraciones pendientes con el 10 % de
| recargo.
|
| Hoy es el 15 de junio de 2026; al obrero lo despidieron el 15 de mayo.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    Mail::fake();

    $this->travelTo(Carbon::parse('2026-06-15 12:00'));

    $this->director = User::factory()->create();
    $this->director->assignRole('admin-uath');
    $this->actingAs($this->director, 'sanctum');

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'OOPP-R', 'nombre' => 'Obras Públicas R', 'nivel' => 1]);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 5, 'regimen_laboral' => 'codigo_trabajo',
    ]);

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->contador = 0;

    /** Un obrero desde el 1 de junio de 2021, que ganaba 620, terminado el 15 de mayo de 2026. */
    $this->despedido = function (string $causal = 'despido_intempestivo'): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'cedula'          => str_pad((string) (8800000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'          => 'Obrero',
            'apellido'        => 'Despedido'.$this->contador,
            'regimen_laboral' => 'codigo_trabajo',
        ]);

        $terminacion = MovimientoPersonal::create([
            'servidor_id'        => $servidor->id,
            'tipo_movimiento'    => TipoMovimientoPersonal::CESACION_FUNCIONES->value,
            'subtipo_movimiento' => $causal,
            'instrumento'        => InstrumentoActo::DOCUMENTO_CT->value,
            'estado'             => EstadoAccionPersonal::REGISTRADA,
            'codigo_registro'    => 'RI-2026-'.str_pad((string) (100 + $this->contador), 4, '0', STR_PAD_LEFT),
            'fecha_registro'     => '2026-05-15',
            'descripcion'        => 'Terminación del contrato de trabajo',
            'fecha_efectiva'     => '2026-05-15',
            'efecto_aplicado_en' => now(),
            'remuneracion_origen' => 620,
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => TipoNombramiento::CODIGO_TRABAJO->value,
            'numero_contrato'          => 'CT-2021-007',
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'lugar_trabajo'            => 'Vía Esmeraldas-Atacames',
            'fecha_inicio'             => '2021-06-01',
            'fecha_fin'                => '2026-05-15',
            'remuneracion'             => 620,
            'estado'                   => 'terminado',
            'movimiento_cierre_id'     => $terminacion->id,
        ]);

        return $servidor->fresh();
    };

    $this->reintegrar = fn (Servidor $s, array $extra = []) => $this->postJson(
        "/api/v1/expediente/servidores/{$s->id}/movimientos",
        [
            'clase'                    => 'restitucion',
            'descripcion'              => 'Reintegro ordenado por el juez',
            'fecha_efectiva'           => '2026-06-15',
            'causa_judicial_numero'    => '08371-2026-00456',
            'unidad_judicial'          => 'Unidad Judicial de Trabajo de Esmeraldas',
            'fecha_providencia'        => '2026-06-10',
            'requiere_dictamen_medico' => false,
            ...$extra,
        ]
    );

    $this->registrar = function (int $id): MovimientoPersonal {
        $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::SUSCRITA);

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };
});

// ── La orden del juez ───────────────────────────────────────────

test('deja sin efecto la terminación y vuelve con su contrato, con la providencia', function () {
    $obrero = ($this->despedido)();
    $terminacion = MovimientoPersonal::where('servidor_id', $obrero->id)->sole();

    ($this->reintegrar)($obrero)
        ->assertCreated()
        ->assertJsonPath('datos.clase', 'restitucion')
        ->assertJsonPath('datos.etiqueta', 'Reintegro por despido ineficaz')
        ->assertJsonPath('datos.instrumento', 'documento_ct')
        ->assertJsonPath('datos.movimiento_relacionado_id', $terminacion->id)
        ->assertJsonPath('datos.puesto_destino_id', $this->puesto->id)
        ->assertJsonPath('datos.tipo_nombramiento_propuesto', 'codigo_trabajo')
        ->assertJsonPath('datos.numero_contrato', 'CT-2021-007')
        ->assertJsonPath('datos.lugar_trabajo', 'Vía Esmeraldas-Atacames')
        ->assertJsonPath('datos.fecha_providencia', fn (string $f) => str_starts_with($f, '2026-06-10'))
        ->assertJsonPath('datos.fecha_sentencia', null);

    expect((float) MovimientoPersonal::where('movimiento_relacionado_id', $terminacion->id)->sole()->remuneracion_propuesta)
        ->toBe(620.0);

    $this->getJson("/api/v1/expediente/movimientos/{$terminacion->id}")
        ->assertOk()
        ->assertJsonPath('datos.restituida_por.etiqueta', 'Reintegro por despido ineficaz')
        ->assertJsonPath('datos.restituida_por.estado', 'borrador');
});

test('basta la providencia; con la sentencia pide su ejecutoría, y no guarda las dos', function () {
    ($this->reintegrar)(($this->despedido)(), ['fecha_providencia' => null, 'unidad_judicial' => null])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'la unidad judicial')
            && str_contains($m, 'la fecha de la providencia que ordena el reintegro'));

    ($this->reintegrar)(($this->despedido)(), ['fecha_sentencia' => '2026-06-01'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'la fecha de ejecutoría de la sentencia'));

    // Con la sentencia, la providencia sobra; y las remuneraciones no son
    // obligatorias: si la sentencia no las fija, se calculan.
    $id = ($this->reintegrar)(($this->despedido)(), [
        'fecha_sentencia'  => '2026-06-01',
        'fecha_ejecutoria' => '2026-06-12',
    ])->assertCreated()->json('datos.id');

    $reintegro = MovimientoPersonal::findOrFail($id);
    expect($reintegro->fecha_providencia)->toBeNull()
        ->and($reintegro->fecha_ejecutoria->toDateString())->toBe('2026-06-12')
        ->and($reintegro->remuneraciones_dejadas_de_percibir)->toBeNull();
});

test('las fechas de la orden tienen su orden', function () {
    $obrero = ($this->despedido)();

    ($this->reintegrar)($obrero, ['fecha_providencia' => '2026-06-20'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fecha_providencia'], 'errores');

    ($this->reintegrar)($obrero, ['fecha_providencia' => '2026-05-15'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'posterior a la terminación'));

    ($this->reintegrar)($obrero, ['fecha_efectiva' => '2026-06-09'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'desde la providencia'));
});

test('corregir el borrador cambia de la providencia a la sentencia sin guardar las dos', function () {
    $id = ($this->reintegrar)(($this->despedido)())->assertCreated()->json('datos.id');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", [
        'fecha_sentencia'  => '2026-06-01',
        'fecha_ejecutoria' => '2026-06-12',
        'lugar_trabajo'    => '',
    ])->assertOk();

    $reintegro = MovimientoPersonal::findOrFail($id);
    expect($reintegro->fecha_providencia)->toBeNull()
        ->and($reintegro->fecha_sentencia->toDateString())->toBe('2026-06-01')
        // Lo del contrato no se corrige en el borrador: el formulario lo manda vacío.
        ->and($reintegro->lugar_trabajo)->toBe('Vía Esmeraldas-Atacames');
});

test('no hay reintegro de quien murió, ni dos de la misma terminación', function () {
    ($this->reintegrar)(($this->despedido)(SubtipoMovimientoPersonal::MUERTE_O_INCAPACIDAD->value))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'muerte o incapacidad'));

    $obrero = ($this->despedido)();
    ($this->reintegrar)($obrero)->assertCreated();
    ($this->reintegrar)($obrero)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ya tiene su restitución'));
});

// ── El efecto ───────────────────────────────────────────────────

test('al registrarse vuelve con su contrato, con la hoja RI, y la antigüedad no se corta', function () {
    $obrero = ($this->despedido)();

    $reintegro = ($this->registrar)(($this->reintegrar)($obrero)->json('datos.id'));
    $vigente = $obrero->fresh('contratoVigente')->contratoVigente;

    expect($reintegro->codigo_registro)->toStartWith('RI-2026-')
        ->and($vigente->tipo_nombramiento)->toBe(TipoNombramiento::CODIGO_TRABAJO)
        ->and($vigente->numero_contrato)->toBe('CT-2021-007')
        ->and((float) $vigente->remuneracion)->toBe(620.0)
        ->and($vigente->lugar_trabajo)->toBe('Vía Esmeraldas-Atacames')
        ->and($vigente->movimiento_origen_id)->toBe($reintegro->id)
        ->and($obrero->fresh()->inicioDelServicioContinuo())->toBe('2021-06-01');

    // Anularlo borra el vínculo y la terminación vuelve a estar sola.
    $this->estados->transicionar($reintegro->fresh(), EstadoAccionPersonal::ANULADA, ['motivo_anulacion' => 'Prueba.']);

    expect($obrero->fresh('contratoVigente')->contratoVigente)->toBeNull()
        ->and(MovimientoPersonal::where('servidor_id', $obrero->id)->where('tipo_movimiento', 'cesacion_funciones')->sole()->restitucion)
        ->toBeNull();
});

test('si su puesto ya no tiene plaza, Talento Humano elige otro de obreros, sin bajarle la remuneración', function () {
    $obrero = ($this->despedido)();
    $otroPuesto = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 2, 'regimen_laboral' => 'codigo_trabajo',
    ]);
    $deLaLosep = Puesto::create([
        'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 2, 'regimen_laboral' => 'losep',
    ]);

    // Con plaza en el suyo, vuelve a él.
    ($this->reintegrar)($obrero, ['puesto_destino_id' => $otroPuesto->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'vuelve a él'));

    // Sin plaza, hay que elegir otro, y de obreros.
    $this->puesto->update(['plazas' => 0]);

    ($this->reintegrar)($obrero)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ya no tiene plaza libre'));

    ($this->reintegrar)($obrero, ['puesto_destino_id' => $deLaLosep->id])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no es del catálogo de obreros'));

    ($this->reintegrar)($obrero, ['puesto_destino_id' => $otroPuesto->id, 'remuneracion_propuesta' => 500])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no baja'));

    ($this->reintegrar)($obrero, ['puesto_destino_id' => $otroPuesto->id])
        ->assertCreated()
        ->assertJsonPath('datos.puesto_destino_id', $otroPuesto->id);
});

// ── Lo que se paga ──────────────────────────────────────────────

test('al reintegro le corresponden las pendientes con el 10 % de recargo, sin montos', function () {
    // Fuera del 16 de mayo al 14 de junio: 30 días.
    $id = ($this->reintegrar)(($this->despedido)())->json('datos.id');
    $liquidacion = app(TerminacionObreroService::class)->loQueCorresponde(MovimientoPersonal::findOrFail($id));

    // Lo calcula Financiero: el SGTH es informativo (2026-10-20).
    expect($liquidacion['tipo'])->toBe('reintegro')
        ->and($liquidacion['dias_fuera'])->toBe(30)
        ->and($liquidacion['desde'])->toBe('2026-05-16')
        ->and($liquidacion['hasta'])->toBe('2026-06-14')
        ->and($liquidacion['rubros'][0]['detalle'])->toBe('Las de los 30 días fuera')
        ->and($liquidacion['rubros'][1]['detalle'])->toBe('10 % de las remuneraciones pendientes')
        ->and($liquidacion)->not->toHaveKey('total')
        ->and($liquidacion['nota'])->toContain('medida cautelar');

    // Las que fija la sentencia mandan, y lo pagado en el finiquito se recuerda.
    $obrero = ($this->despedido)();
    ActaFiniquito::create([
        'movimiento_personal_id' => MovimientoPersonal::where('servidor_id', $obrero->id)->sole()->id,
        'numero'                 => 'SUT-2026-0009',
        'fecha'                  => '2026-05-20',
        'valor_pagado'           => 3500,
        'created_by'             => $this->director->id,
    ]);
    $id = ($this->reintegrar)($obrero, [
        'fecha_sentencia'  => '2026-06-01',
        'fecha_ejecutoria' => '2026-06-12',
        'remuneraciones_dejadas_de_percibir' => 1000,
    ])->json('datos.id');
    $liquidacion = app(TerminacionObreroService::class)->loQueCorresponde(MovimientoPersonal::findOrFail($id));

    expect($liquidacion['rubros'][0]['detalle'])->toBe('Las que fija la sentencia: $1.000,00')
        ->and($liquidacion['nota'])->not->toContain('medida cautelar')
        ->and($liquidacion['nota'])->toContain('se pagaron $3.500,00');
});

test('al registrarse sale por correo a Financiero, y al anularse el aviso contrario', function () {
    $financiera = UnidadAdministrativa::create(['codigo' => 'FIN-R', 'nombre' => 'Gestión Financiera R', 'nivel' => 1]);
    $financiera->forceFill(['es_unidad_financiera' => true])->save();
    $puestoJefe = Puesto::create(['unidad_administrativa_id' => $financiera->id, 'plazas' => 1, 'es_jefe' => true]);
    $jefe = Servidor::create(['cedula' => '0912345679', 'nombre' => 'Jefe', 'apellido' => 'Financiero', 'regimen_laboral' => 'losep']);
    ContratoServidor::create([
        'servidor_id' => $jefe->id, 'tipo_nombramiento' => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $financiera->id, 'puesto_id' => $puestoJefe->id,
        'fecha_inicio' => '2018-01-01', 'estado' => 'vigente',
    ]);
    User::factory()->create(['servidor_id' => $jefe->id, 'email' => 'jefe.financiero@gadpe.gob.ec']);

    $id = ($this->reintegrar)(($this->despedido)())->json('datos.id');
    $url = "/api/v1/expediente/movimientos/{$id}/transicionar";

    $this->putJson($url, ['estado' => 'suscrita'])->assertOk();
    $this->putJson($url, ['estado' => 'registrada'])->assertOk()->assertJsonPath('meta.aviso_financiero', 'enviado');
    $this->putJson($url, ['estado' => 'anulada', 'motivo_anulacion' => 'Se registró con la causa equivocada.'])
        ->assertOk()
        ->assertJsonPath('meta.aviso_financiero', 'enviado');

    Mail::assertSent(LiquidacionObreroMail::class, fn (LiquidacionObreroMail $m) => ! $m->anulada
        && $m->liquidacion['dias_fuera'] === 30
        && str_contains($m->envelope()->subject, 'Reintegro de obrero'));
    Mail::assertSent(LiquidacionObreroMail::class, fn (LiquidacionObreroMail $m) => $m->anulada);

    $html = (new LiquidacionObreroMail(
        MovimientoPersonal::findOrFail($id)->fresh(), app(TerminacionObreroService::class)->loQueCorresponde(MovimientoPersonal::findOrFail($id)),
        false, '%PDF', 'hoja.pdf',
    ))->render();
    expect($html)->toContain('despido el juez declaró ineficaz')
        ->and($html)->toContain('08371-2026-00456')
        ->and($html)->toContain('providencia del 10/06/2026');
});

test('la terminación reintegrada ya no espera su acta de finiquito', function () {
    $obrero = ($this->despedido)();
    $terminacion = MovimientoPersonal::where('servidor_id', $obrero->id)->sole();

    $this->getJson('/api/v1/expediente/terminaciones-obreros?sin_acta=1')
        ->assertOk()
        ->assertJsonPath('datos.0.movimiento_id', $terminacion->id);

    $reintegro = ($this->registrar)(($this->reintegrar)($obrero)->json('datos.id'));

    $this->getJson('/api/v1/expediente/terminaciones-obreros?sin_acta=1')->assertOk()->assertJsonCount(0, 'datos');
    $this->getJson('/api/v1/expediente/terminaciones-obreros')
        ->assertOk()
        ->assertJsonPath('datos.0.reintegro.codigo_registro', $reintegro->codigo_registro);
});

// ── Catálogo, formulario y hoja ─────────────────────────────────

test('el catálogo dice que es del registro interno, y el servidor, su último nombramiento', function () {
    $clase = collect($this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos.clases'))
        ->firstWhere('codigo', 'restitucion');

    expect($clase['documento_ct_para'])->toBe(['codigo_trabajo']);

    $obrero = ($this->despedido)();

    $this->getJson("/api/v1/expediente/servidores/{$obrero->id}")
        ->assertOk()
        ->assertJsonPath('datos.pendiente_vinculacion', true)
        ->assertJsonPath('datos.ultimo_nombramiento', 'codigo_trabajo');
});

test('la hoja de registro interno dice qué deja sin efecto, la orden y lo que corresponde', function () {
    $obrero = ($this->despedido)();
    $terminacion = MovimientoPersonal::where('servidor_id', $obrero->id)->sole();
    $reintegro = ($this->registrar)(($this->reintegrar)($obrero)->json('datos.id'));

    $html = view('pdf.expediente.registro-interno', [
        'movimiento'         => $reintegro->fresh(['unidadDestino', 'puestoDestino.cargo']),
        'descuento'          => null,
        'corresponde'        => app(TerminacionObreroService::class)->loQueCorresponde($reintegro),
        'anulada'            => false,
        'servidor'           => $obrero,
        'firmaTalentoHumano' => ['rotulo' => RolFirmaAccionPersonal::RESPONSABLE_TALENTO_HUMANO->rotuloDocumento(), 'nombre' => null, 'cargo' => 'Director/a de Talento Humano'],
        'logo'               => public_path('images/logo-gadpe.png'),
    ])->render();

    expect($html)->toContain('Reintegro por despido ineficaz')
        ->toContain("Despido intempestivo ({$terminacion->codigo_registro})")
        ->toContain('08371-2026-00456')
        ->toContain('providencia del 10/06/2026')
        ->toContain('Código del Trabajo, Arts. 195.1 a 195.3')
        ->toContain('Lo que corresponde al reintegro')
        ->toContain('30 días fuera')
        ->not->toContain('$ 682.00');
});
