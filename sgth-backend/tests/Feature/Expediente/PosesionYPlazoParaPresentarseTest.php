<?php

namespace Tests\Feature\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\EstadoAccionPersonal;
use App\Enums\InstrumentoActo;
use App\Enums\TipoMovimientoPersonal;
use App\Models\Asistencia\FeriadoInstitucional;
use App\Models\Estructura\PartidaPresupuestaria;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\MovimientoPersonal;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\AccionPersonalPdfService;
use App\Services\Expediente\MovimientoPersonalStateService;
use App\Services\Expediente\PosesionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Fase 6.4 del diseño de Acciones de Personal: la posesión y el plazo para
| presentarse. El nombramiento queda insubsistente si quien ingresa no se
| presenta en el término de 3 días desde el registro de la acción —hasta 5
| laborables por las circunstancias geográficas—, y el contrato ocasional, si
| no se presenta en 24 horas (Reglamento a la LOSEP, Art. 20). El sistema
| avisa; Talento Humano anula.
|
| Hoy es el lunes 12 de octubre de 2026.
*/
beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    permisosDeAccionesPersonal();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create(['codigo' => 'UATH-POS', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1]);
    $this->puesto = Puesto::create(['codigo' => 'P-POS', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 20]);
    // El ocasional va con la partida de los contratos.
    $this->puestoOcasional = Puesto::create([
        'codigo' => 'P-POS-OC', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 20,
        'partida_presupuestaria_id' => PartidaPresupuestaria::create([
            'codigo' => '510510', 'descripcion' => 'Servicios Personales por Contrato',
            'grupo_gasto' => 'Gastos en Personal', 'activo' => true, 'disponible' => true,
        ])->id,
    ]);

    $this->estados = app(MovimientoPersonalStateService::class);
    $this->contador = 0;

    $this->persona = function (): Servidor {
        $this->contador++;

        return Servidor::create([
            'cedula' => str_pad((string) (6900000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre' => 'Servidor', 'apellido' => 'Posesion'.$this->contador, 'regimen_laboral' => 'losep',
            'fecha_ingreso_institucion' => '2026-10-12',
        ]);
    };

    /** El borrador del ingreso, por el formulario. */
    $this->ingreso = function (Servidor $s, array $extra = [], string $nombramiento = 'nombramiento_permanente') {
        $ocasional = $nombramiento === 'servicios_ocasionales';

        return $this->postJson("/api/v1/expediente/servidores/{$s->id}/movimientos", [
            'clase'                       => 'ingreso',
            'descripcion'                 => 'Ingreso de prueba',
            'fecha_efectiva'              => now()->toDateString(),
            'tipo_nombramiento_propuesto' => $nombramiento,
            'unidad_destino_id'           => $this->unidad->id,
            'puesto_destino_id'           => $ocasional ? $this->puestoOcasional->id : $this->puesto->id,
            'numero_contrato'             => 'CT-POS-0'.$this->contador,
            'remuneracion_propuesta'      => 1200,
            'fecha_fin_propuesta'         => $ocasional ? '2026-12-31' : null,
            'requiere_dictamen_medico'    => false,
            ...$extra,
        ]);
    };

    /**
     * Lo registra. La acción de personal se da por suscrita sin pasar por los
     * firmantes, que no son lo que se prueba aquí; el registro del contrato no
     * los tiene.
     */
    $this->registrar = function (int $id): MovimientoPersonal {
        $m = MovimientoPersonal::findOrFail($id);

        if ($m->instrumento === InstrumentoActo::REGISTRO_CONTRATO) {
            $m = $this->estados->transicionar($m, EstadoAccionPersonal::SUSCRITA);
        } else {
            MovimientoPersonal::whereKey($id)->update(['estado' => EstadoAccionPersonal::SUSCRITA->value]);
        }

        return $this->estados->transicionar(MovimientoPersonal::findOrFail($id), EstadoAccionPersonal::REGISTRADA);
    };

    $this->registrado = fn (array $extra = [], string $nombramiento = 'nombramiento_permanente') => ($this->registrar)(
        ($this->ingreso)(($this->persona)(), $extra, $nombramiento)->assertCreated()->json('datos.id')
    );

    $this->posesion = fn (MovimientoPersonal $m, array $datos) => $this->postJson(
        "/api/v1/expediente/movimientos/{$m->id}/posesion", $datos,
    );
});

// ── El plazo ────────────────────────────────────────────────────

test('el ingreso por acción de personal tiene 3 días hábiles desde el registro, sin contar feriados', function () {
    $accion = ($this->registrado)();

    expect($accion->dias_para_presentarse)->toBe(3)
        ->and($accion->presentacion_vence_el->toDateString())->toBe('2026-10-15');

    // El miércoles, feriado: se corre al viernes.
    FeriadoInstitucional::create(['fecha' => '2026-10-14', 'mes' => 10, 'dia' => 14, 'descripcion' => 'Feriado local', 'es_movil' => true]);

    expect(($this->registrado)()->presentacion_vence_el->toDateString())->toBe('2026-10-16');
});

test('por las circunstancias geográficas son hasta 5 días laborables, y se dice por qué', function () {
    ($this->ingreso)(($this->persona)(), ['dias_para_presentarse' => 5])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'circunstancias geográficas'));

    ($this->ingreso)(($this->persona)(), ['dias_para_presentarse' => 6])->assertStatus(422);

    $accion = ($this->registrado)(['dias_para_presentarse' => 5, 'razon_dias_adicionales' => 'Viene desde Galápagos.']);

    expect($accion->dias_para_presentarse)->toBe(5)
        ->and($accion->razon_dias_adicionales)->toBe('Viene desde Galápagos.')
        ->and($accion->presentacion_vence_el->toDateString())->toBe('2026-10-19');
});

test('si rige más tarde, el plazo cuenta desde el día en que rige', function () {
    $accion = ($this->registrado)(['fecha_efectiva' => '2026-10-19']);

    expect($accion->pendienteDeVigencia())->toBeTrue()
        ->and($accion->presentacion_vence_el->toDateString())->toBe('2026-10-22');
});

test('el ocasional tiene hasta el día hábil siguiente, y no lleva término propio', function () {
    // Viernes: las 24 horas caen el lunes.
    $this->travelTo(Carbon::parse('2026-10-16 10:00'));

    $registro = ($this->registrado)(
        ['dias_para_presentarse' => 5, 'razon_dias_adicionales' => 'No aplica.'],
        'servicios_ocasionales',
    );

    expect($registro->instrumento)->toBe(InstrumentoActo::REGISTRO_CONTRATO)
        ->and($registro->dias_para_presentarse)->toBeNull()
        ->and($registro->razon_dias_adicionales)->toBeNull()
        ->and($registro->presentacion_vence_el->toDateString())->toBe('2026-10-19');
});

test('el término se corrige en el borrador, y más de 3 días siguen pidiendo la razón', function () {
    $id = ($this->ingreso)(($this->persona)())->assertCreated()->json('datos.id');

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['dias_para_presentarse' => 4])->assertStatus(422);

    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['dias_para_presentarse' => 4, 'razon_dias_adicionales' => 'Vive en Eloy Alfaro.'])
        ->assertOk()
        ->assertJsonPath('datos.dias_para_presentarse', 4);

    // De vuelta a 3, la razón sobra.
    $this->putJson("/api/v1/expediente/movimientos/{$id}", ['dias_para_presentarse' => 3])
        ->assertOk()
        ->assertJsonPath('datos.razon_dias_adicionales', null);
});

test('el ascenso, el definitivo y el encargo llevan posesión sin plazo; la cesación y el obrero, ninguno', function () {
    $acto = fn (ClaseAccionPersonal $clase, TipoMovimientoPersonal $tipo, InstrumentoActo $instrumento = InstrumentoActo::ACCION_PERSONAL) => (new MovimientoPersonal)
        ->forceFill(['clase' => $clase, 'tipo_movimiento' => $tipo, 'instrumento' => $instrumento,
            'fecha_registro' => '2026-10-12', 'fecha_efectiva' => '2026-10-12']);

    $posesiones = app(PosesionService::class);

    foreach ([
        [ClaseAccionPersonal::ASCENSO, TipoMovimientoPersonal::ASCENSO],
        [ClaseAccionPersonal::NOMBRAMIENTO_DEFINITIVO, TipoMovimientoPersonal::NOMBRAMIENTO_DEFINITIVO],
        [ClaseAccionPersonal::ENCARGO, TipoMovimientoPersonal::SUBROGACION],
    ] as [$clase, $tipo]) {
        $m = $acto($clase, $tipo);
        expect(PosesionService::admitePosesion($m))->toBeTrue($clase->value)
            ->and($posesiones->venceEl($m))->toBeNull();
    }

    $cesacion = $acto(ClaseAccionPersonal::CESACION, TipoMovimientoPersonal::CESACION_FUNCIONES);
    $obrero = $acto(ClaseAccionPersonal::INGRESO, TipoMovimientoPersonal::INGRESO, InstrumentoActo::DOCUMENTO_CT);

    expect(PosesionService::admitePosesion($cesacion))->toBeFalse()
        ->and(PosesionService::admitePosesion($obrero))->toBeFalse()
        ->and($posesiones->venceEl($obrero))->toBeNull();
});

// ── La posesión ─────────────────────────────────────────────────

test('la posesión se registra una sola vez, desde el día en que rige y con el lugar', function () {
    $accion = ($this->registrado)();

    $this->getJson("/api/v1/expediente/movimientos/{$accion->id}")
        ->assertJsonPath('datos.puede_registrar_posesion', true)
        ->assertJsonPath('datos.admite_posesion', true);

    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-12'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'lugar'));
    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-11', 'lugar_posesion' => 'Esmeraldas'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'anterior al día en que rige'));
    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-13', 'lugar_posesion' => 'Esmeraldas'])->assertStatus(422);

    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-12', 'lugar_posesion' => 'Esmeraldas'])
        ->assertOk()
        ->assertJsonPath('datos.fecha_posesion', '2026-10-12')
        ->assertJsonPath('datos.lugar_posesion', 'Esmeraldas')
        ->assertJsonPath('datos.posesion_fuera_de_plazo', false)
        ->assertJsonPath('datos.puede_registrar_posesion', false);

    expect($accion->fresh()->posesion_registrada_por)->toBe($this->user->id);

    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-12', 'lugar_posesion' => 'Quinindé'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'ya está registrada'));
});

test('el ocasional se presenta a laborar sin lugar', function () {
    $registro = ($this->registrado)([], 'servicios_ocasionales');

    ($this->posesion)($registro, ['fecha_posesion' => '2026-10-12'])
        ->assertOk()
        ->assertJsonPath('datos.fecha_posesion', '2026-10-12')
        ->assertJsonPath('datos.lugar_posesion', null);
});

test('la que rige más tarde no admite la posesión todavía', function () {
    $accion = ($this->registrado)(['fecha_efectiva' => '2026-10-19']);

    $this->getJson("/api/v1/expediente/movimientos/{$accion->id}")
        ->assertJsonPath('datos.puede_registrar_posesion', false);

    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-12', 'lugar_posesion' => 'Esmeraldas'])->assertStatus(422);
});

test('fuera del plazo se registra igual, y queda marcada', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $accion = ($this->registrado)();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-12', 'lugar_posesion' => 'Esmeraldas'])
        ->assertOk()
        ->assertJsonPath('datos.posesion_fuera_de_plazo', true)
        ->assertJsonPath('datos.presentacion_vencida', false);
});

test('nadie registra la posesión de su propio ingreso', function () {
    $accion = ($this->registrado)();
    $this->user->update(['servidor_id' => $accion->servidor_id]);

    $this->getJson("/api/v1/expediente/movimientos/{$accion->id}")
        ->assertJsonPath('datos.puede_registrar_posesion', false);

    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-12', 'lugar_posesion' => 'Esmeraldas'])->assertStatus(422);

    expect($accion->fresh()->fecha_posesion)->toBeNull();
});

test('el documento imprime el lugar y la fecha de la posesión', function () {
    $accion = ($this->registrado)();
    ($this->posesion)($accion, ['fecha_posesion' => '2026-10-12', 'lugar_posesion' => 'Muisne'])->assertOk();

    ['vista' => $vista, 'datos' => $datos] = app(AccionPersonalPdfService::class)->documento($accion->id);
    $texto = preg_replace('/\s+/', ' ', strip_tags(str_replace('</td>', '</td> ', view($vista, $datos)->render())));

    expect($texto)->toContain('Lugar Muisne Fecha 12/10/2026');
});

// ── El aviso ────────────────────────────────────────────────────

test('«Sin posesión» lista lo que espera, lo más urgente primero, y no lo anterior a la fase', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $vencida = ($this->registrado)();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));
    $enPlazo = ($this->registrado)();
    $posesionada = ($this->registrado)();
    ($this->posesion)($posesionada, ['fecha_posesion' => '2026-10-12', 'lugar_posesion' => 'Esmeraldas'])->assertOk();

    // Un ingreso registrado antes de la fase: no tiene plazo guardado.
    $anterior = ($this->registrado)();
    MovimientoPersonal::whereKey($anterior->id)->update(['presentacion_vence_el' => null]);

    $respuesta = $this->getJson('/api/v1/expediente/movimientos?sin_posesion=1')->assertOk();

    expect(collect($respuesta->json('datos.data'))->pluck('id')->all())->toBe([$vencida->id, $enPlazo->id])
        ->and($respuesta->json('datos.data.0.presentacion_vencida'))->toBeTrue()
        ->and($respuesta->json('datos.data.1.presentacion_vencida'))->toBeFalse();
});

test('el comando diario avisa de los plazos vencidos', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    ($this->registrado)();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->artisan('sgth:acciones:aplicar-vigentes', ['--fecha' => '2026-10-12'])
        ->expectsOutputToContain('1 ingreso(s) sin posesión con el plazo para presentarse vencido')
        ->assertSuccessful();
});

test('anulado, el ingreso deja de esperar la posesión', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $accion = ($this->registrado)();
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));

    $this->estados->transicionar($accion->fresh(), EstadoAccionPersonal::ANULADA, [
        'motivo_anulacion' => 'No se presentó en el término de 3 días: el nombramiento queda insubsistente (Reglamento a la LOSEP, Art. 20).',
    ]);

    expect(MovimientoPersonal::query()->sinPosesion()->pluck('id')->all())->toBe([])
        ->and($accion->fresh()->presentacionVencida())->toBeFalse();
});
