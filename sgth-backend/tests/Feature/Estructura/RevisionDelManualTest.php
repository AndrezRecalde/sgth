<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\EquivalenciaUnidadManual;
use App\Models\Estructura\FichaManualPuestos;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use App\Services\Estructura\Manual\ImportacionDelManualService;
use App\Services\Estructura\Manual\SugerenciaDePuestos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| La revisión de las fichas del Manual de Puestos 2018-2019
| (docs/diseno-manual-de-puestos.md, P4; PR 12): la unidad de hoy de cada
| gestión del manual, el puesto que se sugiere para cada ficha y la carga del
| perfil que TH confirma.
*/

const RUTA_MANUAL = '/api/v1/estructura/manual-de-puestos';

function usuarioRevisionManual(string $rol, array $permisos = []): User
{
    $r = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    foreach ($permisos as $permiso) {
        $r->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }
    $u = User::factory()->create();
    $u->assignRole($r);

    return $u;
}

/** Un grupo del escalafón con su grado, como el de las fichas. */
function grupoDeGrado(int $grado): GrupoOcupacional
{
    return GrupoOcupacional::firstOrCreate(['grado_codigo' => "SPR{$grado}"], [
        'grado_numerico'        => $grado,
        'grupo'                 => "Servidor Público de grado {$grado}",
        'denominacion_generica' => 'Profesional',
        'rmu'                   => 1000 + $grado,
        'regimen'               => 'losep',
        'activo'                => true,
    ]);
}

function puestoDeGrado(UnidadAdministrativa $unidad, string $cargo, int $grado, array $atributos = []): Puesto
{
    return puestoDePrueba($unidad, $cargo, ['grupo_ocupacional_id' => grupoDeGrado($grado)->id, ...$atributos]);
}

function competenciaPorNombre(string $nombre): Competencia
{
    return Competencia::where('nombre', $nombre)->firstOrFail();
}

/** Una ficha ya importada, con su propuesta de perfil. */
function fichaParaRevisar(array $cambios = []): FichaManualPuestos
{
    static $n = 0;
    $n++;

    $propuesta = array_merge([
        'mision'                    => 'Administrar la bodega y los activos de la institución.',
        'relaciones'                => 'Todas las unidades.',
        'nivel'                     => 'profesional',
        'area_conocimiento'         => 'Administración',
        'especificidad_experiencia' => 'Manejo de inventarios.',
        'capacitacion'              => 'Control de bienes del sector público.',
        'subproceso'                => 'Bodega y Activos',
        'requisitos'                => [
            ['nivel_instruccion' => 'tercer_nivel', 'detalle' => null, 'meses_experiencia' => 24, 'texto' => 'Tercer Nivel', 'experiencia' => '2 años'],
            // «Hasta 1 año»: sin mínimo.
            ['nivel_instruccion' => 'tecnologico_superior', 'detalle' => null, 'meses_experiencia' => null, 'texto' => 'Tecnólogo', 'experiencia' => 'Hasta 1 año'],
            // Lo que no se pudo leer no entra.
            ['nivel_instruccion' => null, 'detalle' => null, 'meses_experiencia' => 12, 'texto' => '???', 'experiencia' => '1 año'],
        ],
        'actividades'  => [
            ['descripcion' => 'Controla el ingreso y egreso de los bienes de la bodega.', 'conocimientos' => 'Normativa de bienes'],
            ['descripcion' => Str::repeat('Elabora los informes de constatación física de los activos fijos. ', 12), 'conocimientos' => null],
        ],
        'competencias' => [
            ['competencia_id' => competenciaPorNombre('Pensamiento Analítico')->id, 'tipo' => 'tecnica', 'nombre' => 'Pensamiento Analítico', 'nivel' => 'medio', 'numero' => 38],
            ['competencia_id' => competenciaPorNombre('Trabajo en Equipo')->id, 'tipo' => 'conductual', 'nombre' => 'Trabajo en Equipo', 'nivel' => 'alto', 'numero' => 1],
        ],
    ], $cambios['propuesta'] ?? []);
    unset($cambios['propuesta']);

    return FichaManualPuestos::create(array_merge([
        'archivo'           => "3.PROCESOS HABILITANTES DE APOYO/4.GESTION ADMINISTRATIVA/2.BODEGA/{$n}.ANALISTA SP6.pdf",
        'proceso'           => '3.PROCESOS HABILITANTES DE APOYO',
        'carpeta'           => '4.GESTION ADMINISTRATIVA/2.BODEGA',
        'denominacion'      => 'Analista',
        'unidad'            => 'Gestión Administrativa/Bodega y Activos',
        'grupo_ocupacional' => 'Servidor Público 6',
        'grado'             => 12,
        'plazas'            => 1,
        'ficha'             => ['nivel' => 'Profesional', 'rol' => 'Ejecución de Procesos', 'ambito' => 'Provincial', 'instruccion' => []],
        'propuesta'         => $propuesta,
        'avisos'            => [],
    ], $cambios));
}

function filaDeLaFicha(FichaManualPuestos $ficha, array $fichas): array
{
    return collect($fichas)->firstWhere('id', $ficha->id);
}

beforeEach(function () {
    $this->administrativa = unidadDePrueba(['nombre' => 'Gestión Administrativa', 'nivel' => 2]);
    $this->bodega         = puestoDeGrado($this->administrativa, 'Analista de Bodega', 12);
    $this->asistente      = puestoDeGrado($this->administrativa, 'Asistente Administrativo', 10);

    $this->talentoHumano = usuarioRevisionManual('admin-uath', [Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_PUESTOS]);
    $this->lector        = usuarioRevisionManual('consulta-estructura', [Permiso::VER_ESTRUCTURA]);
});

// ── Permisos ─────────────────────────────────────────────────────────────

test('la revisión es de quien gestiona puestos', function () {
    $ficha = fichaParaRevisar();

    $this->actingAs($this->lector, 'sanctum');
    $this->getJson(RUTA_MANUAL.'/fichas')->assertForbidden();
    $this->getJson(RUTA_MANUAL."/fichas/{$ficha->id}")->assertForbidden();
    $this->getJson(RUTA_MANUAL.'/equivalencias')->assertForbidden();
    $this->putJson(RUTA_MANUAL.'/equivalencias', ['gestion' => 'x', 'unidad_administrativa_id' => null])->assertForbidden();
    // El 403 antes que el 422: sin `puesto_id` igual dice que no puede.
    $this->postJson(RUTA_MANUAL."/fichas/{$ficha->id}/cargar")->assertForbidden();
    $this->deleteJson(RUTA_MANUAL."/fichas/{$ficha->id}/puesto")->assertForbidden();

    expect($ficha->refresh()->puesto_id)->toBeNull();
});

// ── Unidades ─────────────────────────────────────────────────────────────

test('la gestión del manual se separa de su subproceso', function (string $unidad, string $gestion, ?string $subproceso) {
    expect(ImportacionDelManualService::partes($unidad))->toBe(['gestion' => $gestion, 'subproceso' => $subproceso]);
})->with([
    ['Gestión Administrativa/ Bodega y Activos', 'Gestión Administrativa', 'Bodega y Activos'],
    ['Gestión de Planificación (Planificación Institucional)', 'Gestión de Planificación', 'Planificación Institucional'],
    ['Gestión de Secretaria General (Documental y Archivo)', 'Gestión de Secretaria General', 'Documental y Archivo'],
    ['Gestion Estratégica - Prefectura', 'Gestion Estratégica - Prefectura', null],
    ['', '', null],
]);

test('sin unidad escrita, la gestión sale de la carpeta del manual', function () {
    $ficha = fichaParaRevisar(['unidad' => null, 'carpeta' => '4.GESTION ADMINISTRATIVA/2.BODEGA']);
    fichaParaRevisar(['unidad' => null, 'carpeta' => '4. GESTION ADMINISTRATIVA/3.ACTIVOS']);
    fichaParaRevisar(['unidad' => 'Gestión Administrativa/Bodega y Activos']);

    expect(SugerenciaDePuestos::gestionDe($ficha))->toBe('GESTION ADMINISTRATIVA');

    // Las tres son la misma gestión, y se nombra como la escribe la ficha, no
    // con las mayúsculas de la carpeta.
    $this->actingAs($this->talentoHumano, 'sanctum');
    $gestiones = $this->getJson(RUTA_MANUAL.'/equivalencias')->assertOk()->json('datos');
    expect($gestiones)->toHaveCount(1)
        ->and($gestiones[0])->toMatchArray(['nombre' => 'Gestión Administrativa', 'fichas' => 3])
        ->and($gestiones[0]['unidad']['id'])->toBe($this->administrativa->id);
});

test('cada gestión del manual sugiere la unidad de nombre parecido, con sus erratas', function () {
    unidadDePrueba(['nombre' => 'Gestión de Talento Humano y Riesgos Laborales', 'nivel' => 2]);
    fichaParaRevisar(['unidad' => 'Gestión Adminstrativa / Bodega y Activos.']);
    fichaParaRevisar(['unidad' => 'Gestión Administrativa/Adquisiciones']);
    fichaParaRevisar(['unidad' => 'Gestión Talento Humano/Seguridad y Salud Ocupacional']);
    fichaParaRevisar(['unidad' => 'Gestión de Asesoría Legal / Patrocinio Jurídico']);

    $this->actingAs($this->talentoHumano, 'sanctum');
    $gestiones = collect($this->getJson(RUTA_MANUAL.'/equivalencias')->assertOk()->json('datos'))->keyBy('gestion');

    expect($gestiones)->toHaveCount(4)
        ->and($gestiones['gestion administrativa']['fichas'])->toBe(1)
        ->and($gestiones['gestion administrativa']['unidad'])->toMatchArray(['id' => $this->administrativa->id, 'origen' => 'sugerida'])
        // La errata es otra clave, con la misma unidad sugerida.
        ->and($gestiones['gestion adminstrativa']['unidad']['id'])->toBe($this->administrativa->id)
        ->and($gestiones['gestion talento humano']['unidad']['nombre'])->toBe('Gestión de Talento Humano y Riesgos Laborales')
        // Ninguna unidad de hoy se le parece: la elige TH.
        ->and($gestiones['gestion de asesoria legal']['unidad'])->toBeNull();
});

test('TH elige la unidad de una gestión, y sin unidad vuelve a la sugerida', function () {
    $procuraduria = unidadDePrueba(['nombre' => 'Gestión de Procuraduría Síndica', 'nivel' => 2]);
    $ficha = fichaParaRevisar(['unidad' => 'Gestión de Asesoría Legal / Patrocinio Jurídico']);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->putJson(RUTA_MANUAL.'/equivalencias', ['gestion' => 'Gestión de Asesoría Legal', 'unidad_administrativa_id' => $procuraduria->id])
        ->assertOk()
        ->assertJsonPath('mensaje', 'Unidad guardada.');

    expect(EquivalenciaUnidadManual::where('gestion', 'gestion de asesoria legal')->value('unidad_administrativa_id'))->toBe($procuraduria->id);
    $fila = filaDeLaFicha($ficha, $this->getJson(RUTA_MANUAL.'/fichas')->json('datos.fichas'));
    expect($fila['unidad_sgth'])->toMatchArray(['id' => $procuraduria->id, 'origen' => 'elegida']);

    $this->putJson(RUTA_MANUAL.'/equivalencias', ['gestion' => 'gestion de asesoria legal', 'unidad_administrativa_id' => null])
        ->assertOk()
        ->assertJsonPath('mensaje', 'La gestión vuelve a la unidad sugerida.');

    expect(EquivalenciaUnidadManual::count())->toBe(0);
});

test('una equivalencia necesita una unidad activa', function () {
    $cerrada = unidadDePrueba(['nombre' => 'Unidad cerrada', 'nivel' => 2, 'estado' => false]);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->putJson(RUTA_MANUAL.'/equivalencias', ['gestion' => 'Gestión X', 'unidad_administrativa_id' => $cerrada->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unidad_administrativa_id'], 'errores');
    $this->putJson(RUTA_MANUAL.'/equivalencias', ['unidad_administrativa_id' => $this->administrativa->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gestion'], 'errores');
});

// ── Sugerencias ──────────────────────────────────────────────────────────

test('la ficha está lista cuando un solo puesto de su unidad coincide en rol y grado', function () {
    $ficha = fichaParaRevisar();
    $this->actingAs($this->talentoHumano, 'sanctum');

    $datos = $this->getJson(RUTA_MANUAL.'/fichas')->assertOk()->json('datos');
    $fila  = filaDeLaFicha($ficha, $datos['fichas']);

    expect($fila['estado'])->toBe('lista')
        ->and($fila['puesto'])->toMatchArray(['id' => $this->bodega->id, 'cargo' => 'Analista de Bodega', 'grado' => 12])
        ->and($fila['gestion'])->toBe('Gestión Administrativa')
        ->and($fila['subproceso'])->toBe('Bodega y Activos')
        ->and($datos['resumen'])->toMatchArray(['lista' => 1, 'para_revisar' => 0, 'cargada' => 0]);
});

test('un grado distinto deja la ficha para revisar, y dice por qué', function () {
    $ficha = fichaParaRevisar(['denominacion' => 'Asistente', 'grado' => 9]);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $fila = filaDeLaFicha($ficha, $this->getJson(RUTA_MANUAL.'/fichas')->json('datos.fichas'));

    expect($fila['estado'])->toBe('para_revisar')
        ->and($fila['puesto']['id'])->toBe($this->asistente->id)
        ->and($fila['motivo'])->toContain('grado 10')->toContain('de 9');
});

test('entre puestos del mismo rol y grado decide la especialidad; si no, TH elige', function () {
    $compras = puestoDeGrado($this->administrativa, 'Analista de Compras Públicas', 12);
    $deBodega   = fichaParaRevisar();
    $deCompras  = fichaParaRevisar(['unidad' => 'Gestión Administrativa/Compras Públicas']);
    $sinPista   = fichaParaRevisar(['unidad' => 'Gestión Administrativa/Adquisiciones']);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $fichas = $this->getJson(RUTA_MANUAL.'/fichas')->json('datos.fichas');

    expect(filaDeLaFicha($deBodega, $fichas))->toMatchArray(['estado' => 'lista'])
        ->and(filaDeLaFicha($deBodega, $fichas)['puesto']['id'])->toBe($this->bodega->id)
        ->and(filaDeLaFicha($deCompras, $fichas)['puesto']['id'])->toBe($compras->id)
        ->and(filaDeLaFicha($sinPista, $fichas)['estado'])->toBe('para_revisar')
        ->and(filaDeLaFicha($sinPista, $fichas)['motivo'])->toContain('Hay 2 puestos parecidos');
});

test('dos fichas que apuntan al mismo puesto quedan para revisar', function () {
    $una  = fichaParaRevisar();
    $otra = fichaParaRevisar(['unidad' => 'Gestión Administrativa/ Bodega y Activos Fijos.']);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $fichas = $this->getJson(RUTA_MANUAL.'/fichas')->json('datos.fichas');

    foreach ([$una, $otra] as $ficha) {
        expect(filaDeLaFicha($ficha, $fichas)['estado'])->toBe('para_revisar')
            ->and(filaDeLaFicha($ficha, $fichas)['motivo'])->toContain('mismo puesto');
    }
});

test('un asistente de apoyo no es un asistente', function () {
    $ficha = fichaParaRevisar(['denominacion' => 'Asistente de Apoyo', 'grado' => 6]);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $fila = filaDeLaFicha($ficha, $this->getJson(RUTA_MANUAL.'/fichas')->json('datos.fichas'));

    expect($fila['estado'])->toBe('sin_puesto')
        ->and($fila['puesto'])->toBeNull()
        ->and($fila['motivo'])->toBe('En Gestión Administrativa no hay un puesto de asistente de apoyo ni de grado 6.');
});

test('sin unidad conocida o sin puestos de plantilla, la ficha no tiene sugerencia', function () {
    $ambiental = unidadDePrueba(['nombre' => 'Gestión Ambiental', 'nivel' => 2]);
    puestoDeContratoDePrueba($ambiental, 'Consultor Ambiental');
    $sinUnidad = fichaParaRevisar(['unidad' => 'Gestión de Asesoría Legal']);
    $sinPuesto = fichaParaRevisar(['unidad' => 'Gestión Ambiental/Calidad Ambiental']);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $fichas = $this->getJson(RUTA_MANUAL.'/fichas')->json('datos.fichas');

    expect(filaDeLaFicha($sinUnidad, $fichas))->toMatchArray(['estado' => 'sin_unidad', 'puesto' => null])
        ->and(filaDeLaFicha($sinUnidad, $fichas)['motivo'])->toContain('«Gestión de Asesoría Legal»')
        // El puesto de contrato no cuenta: el manual es de los de plantilla (P9).
        ->and(filaDeLaFicha($sinPuesto, $fichas))->toMatchArray(['estado' => 'sin_puesto', 'motivo' => 'Gestión Ambiental no tiene puestos de plantilla.']);
});

test('la ficha trae su propuesta y los puestos de su unidad con lo que ya tienen', function () {
    PuestoActividad::create(['puesto_id' => $this->bodega->id, 'descripcion' => 'Despacha bienes.', 'orden' => 1, 'activo' => true]);
    $ficha = fichaParaRevisar(['avisos' => ['«Trabajo en Equipo» está dos veces.']]);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $datos = $this->getJson(RUTA_MANUAL."/fichas/{$ficha->id}")->assertOk()->json('datos');

    expect($datos['avisos'])->toBe(['«Trabajo en Equipo» está dos veces.'])
        ->and($datos['propuesta']['mision'])->toBe('Administrar la bodega y los activos de la institución.')
        ->and($datos['ficha']['rol'])->toBe('Ejecución de Procesos')
        ->and(collect($datos['candidatos'])->pluck('puesto_id')->all())->toBe([$this->bodega->id, $this->asistente->id])
        ->and($datos['candidatos'][0]['coincide'])->toBe(['rol' => true, 'grado' => true, 'especialidad' => true])
        ->and($datos['candidatos'][0]['contenido'])->toMatchArray(['actividades' => 1, 'competencias' => 0])
        ->and($datos['contenido_cargado'])->toBeNull();

    $this->getJson(RUTA_MANUAL.'/fichas/999999')->assertNotFound();
});

// ── Carga ────────────────────────────────────────────────────────────────

test('cargar la ficha reemplaza el perfil, las actividades y las competencias del puesto', function () {
    PuestoActividad::create(['puesto_id' => $this->bodega->id, 'descripcion' => 'Actividad vieja.', 'orden' => 1, 'activo' => true]);
    PuestoCompetencia::create(['puesto_id' => $this->bodega->id, 'competencia_id' => competenciaPorNombre('Orientación de Servicio')->id, 'nivel' => 'bajo']);
    $ficha = fichaParaRevisar();
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->postJson(RUTA_MANUAL."/fichas/{$ficha->id}/cargar", ['puesto_id' => $this->bodega->id])
        ->assertOk()
        ->assertJsonPath('mensaje', 'Perfil cargado: 2 alternativas de instrucción, 2 actividades y 2 competencias.')
        ->assertJsonPath('datos.estado', 'cargada')
        ->assertJsonPath('datos.puesto.id', $this->bodega->id);

    $perfil = $this->bodega->perfil()->with('requisitos')->first();
    expect($perfil->mision)->toBe('Administrar la bodega y los activos de la institución.')
        ->and($perfil->subproceso)->toBe('Bodega y Activos')
        ->and($perfil->nivel->value)->toBe('profesional')
        ->and($perfil->requisitos->map(fn ($r) => [$r->nivel_instruccion->value, $r->meses_experiencia])->all())
        ->toBe([['tercer_nivel', 24], ['tecnologico_superior', 0]]);

    $actividades = $this->bodega->actividades()->orderBy('orden')->get();
    expect($actividades->pluck('orden')->all())->toBe([1, 2])
        ->and($actividades[0]->conocimientos)->toBe('Normativa de bienes')
        // Más de 200 caracteres: la columna ya es texto.
        ->and(mb_strlen($actividades[1]->descripcion))->toBeGreaterThan(200);

    expect($this->bodega->competencias()->with('competencia')->get()->map(fn ($c) => [$c->competencia->nombre, $c->nivel->value])->sortBy(0)->values()->all())
        ->toBe([['Pensamiento Analítico', 'medio'], ['Trabajo en Equipo', 'alto']]);

    $ficha->refresh();
    expect($ficha->puesto_id)->toBe($this->bodega->id)
        ->and($ficha->cargada_por)->toBe($this->talentoHumano->id)
        ->and($ficha->cargada_en)->not->toBeNull();

    $registro = Activity::where('subject_type', Puesto::class)->where('subject_id', $this->bodega->id)
        ->where('description', 'Perfil cargado del Manual de Puestos')->first();
    expect($registro->properties['archivo'])->toBe($ficha->archivo)
        ->and($registro->properties['antes'])->toBe(['actividades' => 1, 'competencias' => 1])
        ->and($registro->causer_id)->toBe($this->talentoHumano->id);

    // Ya cargada, deja de sugerirse: sale como cargada en el listado.
    $datos = $this->getJson(RUTA_MANUAL.'/fichas')->json('datos');
    expect($datos['resumen']['cargada'])->toBe(1)
        ->and(filaDeLaFicha($ficha, $datos['fichas'])['cargada_por'])->not->toBeNull();
});

test('volver a cargar la misma ficha en su puesto no duplica nada', function () {
    $ficha = fichaParaRevisar();
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->postJson(RUTA_MANUAL."/fichas/{$ficha->id}/cargar", ['puesto_id' => $this->bodega->id])->assertOk();
    $this->postJson(RUTA_MANUAL."/fichas/{$ficha->id}/cargar", ['puesto_id' => $this->bodega->id])->assertOk();

    expect($this->bodega->actividades()->count())->toBe(2)
        ->and($this->bodega->competencias()->count())->toBe(2)
        ->and($this->bodega->perfil()->first()->requisitos()->count())->toBe(2);
});

test('un puesto recibe una sola ficha, y una ficha va a un solo puesto', function () {
    $una  = fichaParaRevisar();
    $otra = fichaParaRevisar();
    $this->actingAs($this->talentoHumano, 'sanctum');
    $this->postJson(RUTA_MANUAL."/fichas/{$una->id}/cargar", ['puesto_id' => $this->bodega->id])->assertOk();

    $this->postJson(RUTA_MANUAL."/fichas/{$otra->id}/cargar", ['puesto_id' => $this->bodega->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['puesto_id'], 'errores');
    $this->postJson(RUTA_MANUAL."/fichas/{$una->id}/cargar", ['puesto_id' => $this->asistente->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['puesto_id'], 'errores');

    expect($otra->refresh()->puesto_id)->toBeNull()
        ->and($this->asistente->actividades()->count())->toBe(0);
});

test('no se carga en un puesto eliminado ni sin decir cuál', function () {
    $ficha = fichaParaRevisar();
    $this->asistente->delete();
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->postJson(RUTA_MANUAL."/fichas/{$ficha->id}/cargar", ['puesto_id' => $this->asistente->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['puesto_id'], 'errores');
    $this->postJson(RUTA_MANUAL."/fichas/{$ficha->id}/cargar", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['puesto_id'], 'errores');
});

test('quitar el emparejamiento libera el puesto y deja el perfil cargado', function () {
    $una  = fichaParaRevisar();
    $otra = fichaParaRevisar(['propuesta' => ['mision' => 'Otra misión.']]);
    $this->actingAs($this->talentoHumano, 'sanctum');
    $this->postJson(RUTA_MANUAL."/fichas/{$una->id}/cargar", ['puesto_id' => $this->bodega->id])->assertOk();

    $this->deleteJson(RUTA_MANUAL."/fichas/{$una->id}/puesto")
        ->assertOk()
        ->assertJsonPath('datos.estado', fn ($estado) => $estado !== 'cargada');

    expect($una->refresh()->puesto_id)->toBeNull()
        ->and($una->cargada_por)->toBeNull()
        ->and($this->bodega->perfil()->first()->mision)->toBe('Administrar la bodega y los activos de la institución.');

    $this->postJson(RUTA_MANUAL."/fichas/{$otra->id}/cargar", ['puesto_id' => $this->bodega->id])->assertOk();
    expect($this->bodega->perfil()->first()->mision)->toBe('Otra misión.');
});

// ── Actividades largas ───────────────────────────────────────────────────

test('una actividad del puesto admite el texto largo del manual', function () {
    $this->actingAs($this->talentoHumano, 'sanctum');
    $ruta = "/api/v1/estructura/puestos/{$this->bodega->id}/actividades";

    $this->postJson($ruta, ['descripcion' => Str::repeat('a', 3000), 'conocimientos' => Str::repeat('b', 1000)])->assertCreated();
    $this->postJson($ruta, ['descripcion' => Str::repeat('a', 3001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['descripcion'], 'errores');
});

// ── Con el manual real ───────────────────────────────────────────────────

test('las 179 fichas del manual se listan con su estado', function () {
    Artisan::call('sgth:manual:importar');
    $this->actingAs($this->talentoHumano, 'sanctum');

    $datos = $this->getJson(RUTA_MANUAL.'/fichas')->assertOk()->json('datos');

    expect($datos['fichas'])->toHaveCount(179)
        ->and(array_sum($datos['resumen']))->toBe(179)
        ->and(collect($datos['fichas'])->where('gestion', '')->count())->toBe(0);

    $gestiones = $this->getJson(RUTA_MANUAL.'/equivalencias')->assertOk()->json('datos');
    expect(collect($gestiones)->sum('fichas'))->toBe(179);
});
