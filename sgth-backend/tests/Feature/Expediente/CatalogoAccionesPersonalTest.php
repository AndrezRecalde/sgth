<?php

namespace Tests\Feature\Expediente;

use App\Enums\ClaseAccionPersonal;
use App\Enums\EstadoAccionPersonal;
use App\Enums\FamiliaAccionPersonal;
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
use App\Services\Expediente\MovimientoPersonalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Fase 1.1 del rediseño de Acciones de Personal: el catálogo pasa a servirlo el
| backend y la acción se pide por su clase legal. Estas pruebas fijan dos cosas:
| que el cambio de vocabulario no cambió ninguna regla —lo que Talento Humano
| podía registrar ayer lo puede registrar hoy, a los mismos nombramientos—, y
| que la traducción entre clase y tipo/subtipo es de ida y vuelta.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    Role::firstOrCreate(['name' => 'asistente-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->unidad = UnidadAdministrativa::create([
        'codigo' => 'UATH-CAT', 'nombre' => 'Unidad de Talento Humano', 'nivel' => 1,
    ]);

    $this->puesto = Puesto::create([
        'codigo' => 'P-CAT', 'unidad_administrativa_id' => $this->unidad->id, 'plazas' => 10,
    ]);

    $this->contador = 0;

    $this->servidorCon = function (TipoNombramiento $nombramiento): Servidor {
        $this->contador++;

        $servidor = Servidor::create([
            'user_id'                   => User::factory()->create()->id,
            'cedula'                    => str_pad((string) (8100000000 + $this->contador), 10, '0', STR_PAD_LEFT),
            'nombre'                    => 'Servidor',
            'apellido'                  => 'Catalogo'.$this->contador,
            'regimen_laboral'           => 'losep',
            'puesto_id'                 => $this->puesto->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'fecha_ingreso_institucion' => '2018-01-01',
        ]);

        ContratoServidor::create([
            'servidor_id'              => $servidor->id,
            'tipo_nombramiento'        => $nombramiento->value,
            'unidad_administrativa_id' => $this->unidad->id,
            'puesto_id'                => $this->puesto->id,
            'fecha_inicio'             => '2018-01-01',
            'estado'                   => 'vigente',
        ]);

        return $servidor->fresh('contratoVigente');
    };

    $this->crear = fn (Servidor $servidor, array $datos) => $this->postJson(
        "/api/v1/expediente/servidores/{$servidor->id}/movimientos",
        [
            'descripcion'              => 'Acción de prueba del catálogo',
            'fecha_efectiva'           => '2026-10-15',
            'requiere_dictamen_medico' => false,
            ...$datos,
        ]
    );
});

/** @return list<ClaseAccionPersonal> */
function clasesDelFormulario(): array
{
    return array_values(array_filter(
        ClaseAccionPersonal::cases(),
        fn (ClaseAccionPersonal $c) => $c->seCreaDesdeElFormulario()
    ));
}

// ── El catálogo ─────────────────────────────────────────────────

test('el catálogo lo leen Talento Humano y su asistente, no un servidor', function () {
    $this->getJson('/api/v1/expediente/acciones-personal/catalogo')->assertOk();

    $asistente = User::factory()->create();
    $asistente->assignRole('asistente-uath');
    $this->actingAs($asistente, 'sanctum')
        ->getJson('/api/v1/expediente/acciones-personal/catalogo')
        ->assertOk();

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/expediente/acciones-personal/catalogo')
        ->assertForbidden();
});

test('el catálogo trae todas las familias y todas las clases, con su familia', function () {
    $datos = $this->getJson('/api/v1/expediente/acciones-personal/catalogo')->json('datos');

    expect(array_column($datos['familias'], 'codigo'))
        ->toBe(array_map(fn (FamiliaAccionPersonal $f) => $f->value, FamiliaAccionPersonal::cases()))
        ->and(array_column($datos['clases'], 'codigo'))
        ->toBe(array_map(fn (ClaseAccionPersonal $c) => $c->value, ClaseAccionPersonal::cases()));

    $cesacion = collect($datos['clases'])->firstWhere('codigo', 'cesacion');

    expect($cesacion['familia'])->toBe('cesacion')
        ->and(array_column($cesacion['causales'], 'codigo'))->toBe([
            'renuncia', 'destitucion', 'jubilacion', 'incapacidad', 'contrato_finalizado', 'visto_bueno',
        ])
        // El dictamen médico abre marcado en las dos causales que son
        // determinaciones médicas, igual que antes.
        ->and(collect($cesacion['causales'])->firstWhere('codigo', 'jubilacion')['dictamen_medico_por_defecto'])->toBeTrue()
        ->and(collect($cesacion['causales'])->firstWhere('codigo', 'renuncia')['dictamen_medico_por_defecto'])->toBeFalse();

    $subrogacion = collect($datos['clases'])->firstWhere('codigo', 'subrogacion');

    expect($subrogacion['se_crea_desde_formulario'])->toBeFalse()
        ->and($subrogacion['nombramientos_elegibles'])->toBe([]);
});

// ── Ninguna regla cambió con el vocabulario ─────────────────────

/*
| La matriz de elegibilidad de hoy, escrita a mano a propósito: si el catálogo la
| altera, esta prueba lo dice. Cada fila junta lo que antes estaba repartido en
| varios tipos —el traslado es el «Traspaso» de los permanentes más la
| «Prestación de servicios» de los demás—.
*/
test('cada clase aplica exactamente a los nombramientos de antes', function () {
    $esperado = [
        'traslado' => [
            'nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales',
            'libre_nombramiento_remocion', 'servicios_profesionales',
        ],
        'intercambio_voluntario'    => ['nombramiento_permanente'],
        'comision_con_remuneracion' => ['nombramiento_permanente'],
        'comision_sin_remuneracion' => ['nombramiento_permanente'],
        'licencia_sin_remuneracion' => ['nombramiento_permanente', 'codigo_trabajo', 'eleccion_popular'],
        'incremento_remuneracion'   => ['codigo_trabajo'],
        'cambio_ocupacion'          => ['codigo_trabajo'],
        'sancion' => [
            'nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales',
            'libre_nombramiento_remocion', 'codigo_trabajo',
        ],
        'cesacion' => [
            'nombramiento_permanente', 'nombramiento_provisional', 'servicios_ocasionales',
            'libre_nombramiento_remocion', 'codigo_trabajo', 'servicios_profesionales',
        ],
        // El ingreso no depende del nombramiento: se registra a quien no tiene
        // vínculo. Y la subrogación y el encargo los decide su propio módulo.
        'ingreso'     => [],
        'subrogacion' => [],
        'encargo'     => [],
    ];

    foreach (ClaseAccionPersonal::cases() as $clase) {
        $obtenido = array_map(fn (TipoNombramiento $n) => $n->value, $clase->nombramientosElegibles());

        sort($obtenido);
        $esperadoOrdenado = $esperado[$clase->value];
        sort($esperadoOrdenado);

        expect($obtenido)->toBe($esperadoOrdenado, "Elegibilidad de «{$clase->etiqueta()}»");
    }
});

test('la clase que se guarda es la que se pidió, para cada nombramiento y causal', function () {
    foreach (clasesDelFormulario() as $clase) {
        $causales = $clase->requiereCausal() ? $clase->causales() : [null];

        foreach ($causales as $causal) {
            foreach (TipoNombramiento::cases() as $nombramiento) {
                [$tipo, $subtipo] = $clase->tipoYSubtipo($nombramiento, $causal);

                expect(ClaseAccionPersonal::desde($tipo, $subtipo ?? $tipo->subtipoEquivalente()))
                    ->toBe($clase, "{$clase->etiqueta()} / {$nombramiento->etiqueta()}");
            }
        }
    }
});

test('lo que pide el formulario coincide con lo que la acción hace al registrarse', function () {
    foreach (clasesDelFormulario() as $clase) {
        foreach ($clase->nombramientosElegibles() ?: [null] as $nombramiento) {
            [$tipo, $subtipo] = $clase->tipoYSubtipo($nombramiento, $clase->causales()[0] ?? null);

            $movimiento = new MovimientoPersonal([
                'tipo_movimiento'    => $tipo,
                'subtipo_movimiento' => $subtipo,
            ]);

            $nombre = $clase->etiqueta();

            expect($clase->pideSituacionPropuesta())
                ->toBe($tipo->creaVinculo() || $movimiento->reubicaAlServidor(), "Situación propuesta de «{$nombre}»")
                ->and($clase->pidePeriodo())
                ->toBe((bool) $subtipo?->esComisionDeServicios(), "Período de «{$nombre}»")
                ->and($clase->pideContratacion())
                ->toBe($tipo->creaVinculo(), "Contratación de «{$nombre}»");
        }
    }
});

// ── Crear por clase ─────────────────────────────────────────────

test('el traslado de un permanente se guarda como el traspaso de siempre', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    ($this->crear)($servidor, [
        'clase'             => 'traslado',
        'unidad_destino_id' => $this->unidad->id,
        'puesto_destino_id' => $this->puesto->id,
    ])->assertCreated()
        ->assertJsonPath('datos.clase', 'traslado')
        ->assertJsonPath('datos.etiqueta', 'Traslado')
        ->assertJsonPath('datos.propone_situacion', true);

    $m = MovimientoPersonal::where('servidor_id', $servidor->id)->firstOrFail();

    expect($m->tipo_movimiento)->toBe(TipoMovimientoPersonal::CAMBIO_ADMINISTRATIVO)
        ->and($m->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::TRASPASO)
        ->and($m->clase)->toBe(ClaseAccionPersonal::TRASLADO)
        ->and($m->estado)->toBe(EstadoAccionPersonal::BORRADOR);
});

test('el traslado de un provisional se guarda como la prestación de servicios de siempre', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PROVISIONAL);

    ($this->crear)($servidor, [
        'clase'             => 'traslado',
        'puesto_destino_id' => $this->puesto->id,
    ])->assertCreated();

    $m = MovimientoPersonal::where('servidor_id', $servidor->id)->firstOrFail();

    expect($m->tipo_movimiento)->toBe(TipoMovimientoPersonal::PRESTACION_SERVICIOS)
        ->and($m->subtipo_movimiento)->toBeNull()
        ->and($m->clase)->toBe(ClaseAccionPersonal::TRASLADO)
        ->and($m->reubicaAlServidor())->toBeTrue();
});

test('la cesación exige su causal, y la causal se guarda como su subtipo', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $sinCausal = ($this->crear)($servidor, ['clase' => 'cesacion']);

    $sinCausal->assertStatus(422);
    expect($sinCausal->json('mensaje'))->toContain('Indique la causal')->toContain('Renuncia');

    ($this->crear)($servidor, ['clase' => 'cesacion', 'causal' => 'renuncia'])
        ->assertCreated()
        ->assertJsonPath('datos.etiqueta', 'Cesación de Funciones')
        ->assertJsonPath('datos.causal', 'renuncia')
        ->assertJsonPath('datos.causal_etiqueta', 'Renuncia')
        ->assertJsonPath('datos.toca_el_vinculo', true);

    $m = MovimientoPersonal::where('servidor_id', $servidor->id)->firstOrFail();

    expect($m->tipo_movimiento)->toBe(TipoMovimientoPersonal::CESACION_FUNCIONES)
        ->and($m->subtipo_movimiento)->toBe(SubtipoMovimientoPersonal::RENUNCIA);
});

test('una causal que no aplica al nombramiento se rechaza con su nombre', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    $respuesta = ($this->crear)($obrero, ['clase' => 'cesacion', 'causal' => 'renuncia']);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))
        ->toContain('Cesación de Funciones por Renuncia')
        ->toContain('Código del Trabajo');
});

test('una clase que no aplica al nombramiento se rechaza con el nombre de la clase', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $respuesta = ($this->crear)($permanente, ['clase' => 'incremento_remuneracion']);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))
        ->toContain('Incremento de Remuneración')
        ->toContain('Nombramiento Permanente');
});

test('una clase sin causal no admite una', function () {
    $permanente = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $respuesta = ($this->crear)($permanente, [
        'clase' => 'licencia_sin_remuneracion', 'causal' => 'renuncia',
    ]);

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('no lleva causal');
});

test('la subrogación, el encargo y la bitácora no se crean por la API genérica', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    foreach (['subrogacion', 'encargo', 'novedad_contrato', 'cambio_puesto'] as $clase) {
        ($this->crear)($servidor, ['clase' => $clase])->assertStatus(422);
    }

    expect(MovimientoPersonal::where('servidor_id', $servidor->id)->count())->toBe(0);
});

test('el servicio también rechaza una clase que no es del formulario', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    app(MovimientoPersonalService::class)->registrarPorClase(
        $servidor->id, ClaseAccionPersonal::ENCARGO, null, [
            'descripcion' => 'x', 'fecha_efectiva' => '2026-10-15',
        ]
    );
})->throws(ReglaNegocioException::class, 'se registra desde su propia pantalla');

// ── La clase de lo que se crea por tipo ─────────────────────────

test('lo que los módulos crean por tipo recibe su clase sin pedirla', function () {
    $obrero = ($this->servidorCon)(TipoNombramiento::CODIGO_TRABAJO);

    $m = app(MovimientoPersonalService::class)->registrar($obrero->id, [
        'tipo_movimiento' => TipoMovimientoPersonal::CAMBIO_DENOMINACION->value,
        'descripcion'     => 'Cambio de denominación creado por tipo',
        'fecha_efectiva'  => '2026-10-15',
    ]);

    expect($m->clase)->toBe(ClaseAccionPersonal::CAMBIO_OCUPACION)
        ->and($m->etiqueta())->toBe('Cambio de Ocupación');
});

test('los tipos antiguos se clasifican como el resto del módulo los opera', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $crear = fn (TipoMovimientoPersonal $tipo) => MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => $tipo,
        'descripcion'     => "Fila de tipo {$tipo->value}",
        'fecha_efectiva'  => '2026-10-15',
    ]);

    expect($crear(TipoMovimientoPersonal::TRASPASO)->clase)->toBe(ClaseAccionPersonal::TRASLADO)
        ->and($crear(TipoMovimientoPersonal::TRASLADO)->clase)->toBe(ClaseAccionPersonal::INTERCAMBIO_VOLUNTARIO)
        ->and($crear(TipoMovimientoPersonal::COMISION_SERVICIOS)->clase)->toBe(ClaseAccionPersonal::COMISION_CON_REMUNERACION)
        ->and($crear(TipoMovimientoPersonal::DESTITUCION)->causal())->toBe(SubtipoMovimientoPersonal::DESTITUCION);
});

test('un encargo sigue siendo encargo aunque la acción se edite', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $m = MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::SUBROGACION,
        'clase'           => ClaseAccionPersonal::ENCARGO,
        'estado'          => EstadoAccionPersonal::BORRADOR,
        'descripcion'     => 'Encargo de un puesto directivo vacante',
        'fecha_efectiva'  => '2026-10-15',
    ]);

    $m->update(['descripcion' => 'Encargo corregido']);

    expect($m->fresh()->clase)->toBe(ClaseAccionPersonal::ENCARGO)
        ->and($m->fresh()->etiqueta())->toBe('Encargo')
        ->and($m->fresh()->editableEnFormulario())->toBeFalse();
});

test('la clase de una acción registrada no se reescribe', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $m = MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION,
        'estado'          => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro' => 'AP-2026-0901',
        'fecha_registro'  => now(),
        'descripcion'     => 'Licencia registrada',
        'fecha_efectiva'  => '2026-10-15',
    ]);

    $m->update(['clase' => ClaseAccionPersonal::TRASLADO]);
})->throws(ReglaNegocioException::class, "'clase'");

test('una acción leída con select parcial se guarda sin tocar su clase', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    $m = MovimientoPersonal::create([
        'servidor_id'     => $servidor->id,
        'tipo_movimiento' => TipoMovimientoPersonal::LICENCIA_SIN_REMUNERACION,
        'estado'          => EstadoAccionPersonal::REGISTRADA,
        'codigo_registro' => 'AP-2026-0902',
        'fecha_registro'  => now(),
        'descripcion'     => 'Licencia registrada',
        'fecha_efectiva'  => '2026-10-15',
    ]);

    $parcial = MovimientoPersonal::select(['id', 'estado', 'descripcion'])->findOrFail($m->id);
    $parcial->descripcion = 'Nota sin tocar la clase';
    $parcial->save();

    expect($m->fresh()->clase)->toBe(ClaseAccionPersonal::LICENCIA_SIN_REMUNERACION);
});

// ── Lo que la bandeja recibe ────────────────────────────────────

test('la bandeja entrega el nombre y lo que la pantalla decide con cada acción', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    ($this->crear)($servidor, ['clase' => 'comision_con_remuneracion',
        'fecha_inicio' => '2026-11-01', 'fecha_fin' => '2027-11-01'])->assertCreated();

    $fila = $this->getJson('/api/v1/expediente/movimientos')->json('datos.data.0');

    expect($fila['clase'])->toBe('comision_con_remuneracion')
        ->and($fila['familia'])->toBe('cambio_administrativo')
        ->and($fila['etiqueta'])->toBe('Comisión de Servicios con Remuneración')
        ->and($fila['es_ausencia_temporal'])->toBeTrue()
        ->and($fila['toca_el_vinculo'])->toBeFalse()
        ->and($fila['editable_en_formulario'])->toBeTrue()
        ->and($fila['tiene_documento_imprimible'])->toBeTrue()
        // La bandeja sigue paginando con la forma de siempre.
        ->and($this->getJson('/api/v1/expediente/movimientos')->json('datos.total'))->toBe(1);
});

test('la bandeja filtra por clase', function () {
    $servidor = ($this->servidorCon)(TipoNombramiento::PERMANENTE);

    ($this->crear)($servidor, ['clase' => 'licencia_sin_remuneracion'])->assertCreated();
    ($this->crear)($servidor, ['clase' => 'cesacion', 'causal' => 'jubilacion'])->assertCreated();

    $filas = $this->getJson('/api/v1/expediente/movimientos?clase=cesacion')->json('datos.data');

    expect($filas)->toHaveCount(1)
        ->and($filas[0]['causal'])->toBe('jubilacion');
});
