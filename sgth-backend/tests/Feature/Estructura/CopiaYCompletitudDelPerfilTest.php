<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\FichaManualPuestos;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| Copiar el perfil de otro puesto y el tablero de completitud
| (docs/diseno-manual-de-puestos.md, P6; PR 15).
*/

const RUTA_COMPLETITUD = '/api/v1/estructura/completitud-de-perfiles';

function rutaCopiaDelPerfil(Puesto $puesto): string
{
    return "/api/v1/estructura/puestos/{$puesto->id}/perfil/copia";
}

function usuarioCopiaDelPerfil(string $rol, array $permisos = []): User
{
    $r = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    foreach ($permisos as $permiso) {
        $r->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }
    $u = User::factory()->create();
    $u->assignRole($r);

    return $u;
}

/** Un puesto de la LOSEP con su grado: del 8 al 13 se exige en medio, hasta el 7 en bajo (D5). */
function puestoParaCopiar(UnidadAdministrativa $unidad, string $cargo, int $grado, array $atributos = []): Puesto
{
    $grupo = GrupoOcupacional::firstOrCreate(['grado_codigo' => "SPK{$grado}"], [
        'grado_numerico'        => $grado,
        'grupo'                 => "Servidor Público de grado {$grado}",
        'denominacion_generica' => 'Profesional',
        'rmu'                   => 1000 + $grado,
        'regimen'               => 'losep',
        'activo'                => true,
    ]);

    return puestoDePrueba($unidad, $cargo, ['grupo_ocupacional_id' => $grupo->id, ...$atributos]);
}

function competenciaParaCopiar(string $nombre): Competencia
{
    return Competencia::where('nombre', $nombre)->firstOrFail();
}

/** El perfil completo de «Analista de Bodega», el puesto del que se copia. */
function perfilDeOrigen(Puesto $puesto): void
{
    $perfil = $puesto->perfil()->create([
        'mision'                    => 'Administrar la bodega y los activos de la institución.',
        'relaciones'                => 'Todas las unidades.',
        'nivel'                     => 'profesional',
        'area_conocimiento'         => 'Administración',
        'especificidad_experiencia' => 'Manejo de inventarios.',
        'capacitacion'              => 'Control de bienes del sector público.',
        'subproceso'                => 'Bodega y Activos',
    ]);
    $perfil->requisitos()->create(['nivel_instruccion' => 'tercer_nivel', 'detalle' => 'Administración', 'meses_experiencia' => 24, 'orden' => 1]);
    $perfil->requisitos()->create(['nivel_instruccion' => 'tecnologico_superior', 'detalle' => null, 'meses_experiencia' => 36, 'orden' => 2]);

    PuestoActividad::create(['puesto_id' => $puesto->id, 'descripcion' => 'Controla el ingreso y egreso de los bienes.', 'producto' => 'Kárdex', 'frecuencia' => 'diario_ordinario', 'conocimientos' => 'Normativa de bienes', 'orden' => 1, 'activo' => true]);
    PuestoActividad::create(['puesto_id' => $puesto->id, 'descripcion' => 'Actividad que ya no hace.', 'orden' => 2, 'activo' => false]);
    PuestoActividad::create(['puesto_id' => $puesto->id, 'descripcion' => 'Elabora las actas de constatación física.', 'orden' => 3, 'activo' => true]);

    PuestoCompetencia::create(['puesto_id' => $puesto->id, 'competencia_id' => competenciaParaCopiar('Pensamiento Analítico')->id, 'nivel' => 'medio']);
    PuestoCompetencia::create(['puesto_id' => $puesto->id, 'competencia_id' => competenciaParaCopiar('Trabajo en Equipo')->id, 'nivel' => 'alto']);
}

function ocuparPuestoParaCopiar(Puesto $puesto, string $cedula): void
{
    Servidor::unguard();
    $servidor = Servidor::create(['cedula' => $cedula, 'nombre' => 'Prueba', 'apellido' => 'Perfil', 'estado' => true]);
    ContratoServidor::create([
        'servidor_id'              => $servidor->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $puesto->unidad_administrativa_id,
        'puesto_id'                => $puesto->id,
        'fecha_inicio'             => '2026-01-01',
        'estado'                   => 'vigente',
        'origen'                   => 'accion_personal',
    ]);
}

beforeEach(function () {
    $this->administrativa = unidadDePrueba(['nombre' => 'Gestión Administrativa', 'nivel' => 2]);
    $this->bodega         = puestoParaCopiar($this->administrativa, 'Analista de Bodega', 12);
    $this->activos        = puestoParaCopiar($this->administrativa, 'Analista de Activos Fijos', 12);
    $this->asistente      = puestoParaCopiar($this->administrativa, 'Asistente de Bodega', 5);
    perfilDeOrigen($this->bodega);

    $this->talentoHumano = usuarioCopiaDelPerfil('admin-uath', [Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_PUESTOS]);
    $this->lector        = usuarioCopiaDelPerfil('consulta-estructura', [Permiso::VER_ESTRUCTURA]);
});

// ── Permisos ─────────────────────────────────────────────────────────────

test('copiar el perfil y ver el tablero es de quien gestiona puestos', function () {
    $this->actingAs($this->lector, 'sanctum');

    $this->getJson(rutaCopiaDelPerfil($this->activos).'?origen_id='.$this->bodega->id)->assertForbidden();
    // El 403 antes que el 422: sin `origen_id` igual dice que no puede.
    $this->postJson(rutaCopiaDelPerfil($this->activos))->assertForbidden();
    $this->getJson(RUTA_COMPLETITUD)->assertForbidden();

    expect($this->activos->actividades()->count())->toBe(0);
});

// ── Copiar ───────────────────────────────────────────────────────────────

test('la vista previa dice qué se copia y qué se reemplaza, sin tocar nada', function () {
    PuestoActividad::create(['puesto_id' => $this->activos->id, 'descripcion' => 'Actividad propia.', 'orden' => 1, 'activo' => true]);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $datos = $this->getJson(rutaCopiaDelPerfil($this->activos).'?origen_id='.$this->bodega->id)->assertOk()->json('datos');

    expect($datos['origen'])->toMatchArray([
        'id'     => $this->bodega->id,
        'cargo'  => 'Analista de Bodega',
        'unidad' => 'Gestión Administrativa',
        'grado'  => 'SPK12',
        'mision' => 'Administrar la bodega y los activos de la institución.',
    ])
        // La actividad desactivada no se copia.
        ->and($datos['copia'])->toBe(['mision' => true, 'requisitos' => 2, 'actividades' => 2, 'competencias' => 2])
        ->and($datos['omitidas'])->toBe(['actividades' => 1, 'competencias' => 0])
        ->and($datos['ajuste_de_nivel'])->toBeNull()
        ->and($datos['reemplaza'])->toBe(['mision' => false, 'requisitos' => 0, 'actividades' => 1, 'competencias' => 0])
        ->and($datos['impedimento'])->toBeNull();

    expect($this->activos->actividades()->pluck('descripcion')->all())->toBe(['Actividad propia.'])
        ->and($this->activos->perfil()->exists())->toBeFalse();
});

test('copiar reemplaza el perfil, las actividades y las competencias, y deja el subproceso', function () {
    $this->activos->perfil()->create(['mision' => 'Misión vieja.', 'subproceso' => 'Activos Fijos']);
    PuestoActividad::create(['puesto_id' => $this->activos->id, 'descripcion' => 'Actividad vieja.', 'orden' => 1, 'activo' => true]);
    PuestoCompetencia::create(['puesto_id' => $this->activos->id, 'competencia_id' => competenciaParaCopiar('Orientación de Servicio')->id, 'nivel' => 'bajo']);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->postJson(rutaCopiaDelPerfil($this->activos), ['origen_id' => $this->bodega->id])
        ->assertOk()
        ->assertJsonPath('mensaje', 'Copiado de «Analista de Bodega»: la misión, 2 alternativas de instrucción, 2 actividades y 2 competencias.')
        ->assertJsonPath('datos.ajuste_de_nivel', null);

    $perfil = $this->activos->perfil()->with('requisitos')->first();
    expect($perfil->mision)->toBe('Administrar la bodega y los activos de la institución.')
        ->and($perfil->relaciones)->toBe('Todas las unidades.')
        ->and($perfil->capacitacion)->toBe('Control de bienes del sector público.')
        ->and($perfil->nivel->value)->toBe('profesional')
        // El subproceso dice dónde está el puesto: se queda el suyo.
        ->and($perfil->subproceso)->toBe('Activos Fijos')
        ->and($perfil->requisitos->map(fn ($r) => [$r->nivel_instruccion->value, $r->detalle, $r->meses_experiencia])->all())
        ->toBe([['tercer_nivel', 'Administración', 24], ['tecnologico_superior', null, 36]]);

    $actividades = $this->activos->actividades()->get();
    expect($actividades->map(fn ($a) => [$a->orden, $a->descripcion, $a->activo])->all())->toBe([
        [1, 'Controla el ingreso y egreso de los bienes.', true],
        [2, 'Elabora las actas de constatación física.', true],
    ])
        ->and($actividades[0]->producto)->toBe('Kárdex')
        ->and($actividades[0]->frecuencia->value)->toBe('diario_ordinario')
        ->and($actividades[0]->conocimientos)->toBe('Normativa de bienes');

    expect($this->activos->competencias()->with('competencia')->get()->map(fn ($c) => [$c->competencia->nombre, $c->nivel->value])->sortBy(0)->values()->all())
        ->toBe([['Pensamiento Analítico', 'medio'], ['Trabajo en Equipo', 'alto']]);

    // El otro puesto no cambia.
    expect($this->bodega->actividades()->count())->toBe(3)
        ->and($this->bodega->competencias()->count())->toBe(2);

    $registro = Activity::where('subject_type', Puesto::class)->where('subject_id', $this->activos->id)
        ->where('description', 'Perfil copiado de otro puesto')->first();
    expect($registro->properties['origen'])->toBe(['id' => $this->bodega->id, 'cargo' => 'Analista de Bodega', 'unidad' => 'Gestión Administrativa'])
        ->and($registro->properties['antes'])->toBe(['mision' => true, 'requisitos' => 0, 'actividades' => 1, 'competencias' => 1])
        ->and($registro->causer_id)->toBe($this->talentoHumano->id);

    // Y el cambio del perfil, con lo que tenía antes y de dónde salió.
    $perfilRegistro = Activity::where('subject_id', $this->activos->id)->where('description', 'Perfil del puesto actualizado')->first();
    expect($perfilRegistro->properties['causa'])->toBe('Copia del perfil de «Analista de Bodega»')
        ->and($perfilRegistro->properties['antes']['mision'])->toBe('Misión vieja.');
});

test('el nivel de las competencias sigue al grado del puesto que recibe la copia', function () {
    $this->actingAs($this->talentoHumano, 'sanctum');

    // De grado 12 (medio) a grado 5 (bajo).
    $this->getJson(rutaCopiaDelPerfil($this->asistente).'?origen_id='.$this->bodega->id)
        ->assertOk()
        ->assertJsonPath('datos.ajuste_de_nivel', ['de' => 'medio', 'a' => 'bajo', 'competencias' => 1]);

    $this->postJson(rutaCopiaDelPerfil($this->asistente), ['origen_id' => $this->bodega->id])
        ->assertOk()
        ->assertJsonPath('mensaje', 'Copiado de «Analista de Bodega»: la misión, 2 alternativas de instrucción, 2 actividades y 2 competencias. Una competencia del nivel medio pasó al bajo, el del grado de este puesto.');

    // La que estaba en el nivel del grado baja; la que alguien subió a alto se queda.
    expect($this->asistente->competencias()->with('competencia')->get()->map(fn ($c) => [$c->competencia->nombre, $c->nivel->value])->sortBy(0)->values()->all())
        ->toBe([['Pensamiento Analítico', 'bajo'], ['Trabajo en Equipo', 'alto']]);
});

test('las competencias retiradas del diccionario no se copian', function () {
    competenciaParaCopiar('Trabajo en Equipo')->update(['activo' => false]);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->getJson(rutaCopiaDelPerfil($this->activos).'?origen_id='.$this->bodega->id)
        ->assertJsonPath('datos.copia.competencias', 1)
        ->assertJsonPath('datos.omitidas.competencias', 1);

    $this->postJson(rutaCopiaDelPerfil($this->activos), ['origen_id' => $this->bodega->id])->assertOk();

    expect($this->activos->competencias()->with('competencia')->get()->map(fn ($c) => $c->competencia->nombre)->all())
        ->toBe(['Pensamiento Analítico']);
});

test('un puesto con una ficha del manual cargada no recibe copias', function () {
    FichaManualPuestos::create([
        'archivo'      => '3.PROCESOS HABILITANTES DE APOYO/4.GESTION ADMINISTRATIVA/ANALISTA SP6.pdf',
        'proceso'      => '3.PROCESOS HABILITANTES DE APOYO',
        'carpeta'      => '4.GESTION ADMINISTRATIVA',
        'denominacion' => 'Analista',
        'grado'        => 12,
        'ficha'        => [],
        'propuesta'    => [],
        'avisos'       => [],
        'puesto_id'    => $this->activos->id,
    ]);
    $mensaje = 'Este puesto tiene cargada la ficha «3.PROCESOS HABILITANTES DE APOYO/4.GESTION ADMINISTRATIVA/ANALISTA SP6.pdf» del Manual de Puestos. Para reemplazar su perfil, quite antes ese emparejamiento en la pestaña «Manual».';
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->getJson(rutaCopiaDelPerfil($this->activos).'?origen_id='.$this->bodega->id)
        ->assertOk()
        ->assertJsonPath('datos.impedimento', $mensaje);
    $this->postJson(rutaCopiaDelPerfil($this->activos), ['origen_id' => $this->bodega->id])
        ->assertUnprocessable()
        ->assertJsonPath('errores.origen_id.0', $mensaje);

    expect($this->activos->actividades()->count())->toBe(0);
});

test('no se copia del mismo puesto, de uno sin perfil ni de uno eliminado', function () {
    $vacio     = puestoParaCopiar($this->administrativa, 'Analista de Compras', 12);
    $eliminado = puestoParaCopiar($this->administrativa, 'Analista de Archivo', 12);
    perfilDeOrigen($eliminado);
    $eliminado->delete();
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->postJson(rutaCopiaDelPerfil($this->bodega), ['origen_id' => $this->bodega->id])
        ->assertUnprocessable()
        ->assertJsonPath('errores.origen_id.0', 'Elija otro puesto: es el mismo.');
    $this->postJson(rutaCopiaDelPerfil($this->activos), ['origen_id' => $vacio->id])
        ->assertUnprocessable()
        ->assertJsonPath('errores.origen_id.0', 'El puesto elegido no tiene perfil que copiar.');
    $this->postJson(rutaCopiaDelPerfil($this->activos), ['origen_id' => $eliminado->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origen_id'], 'errores');
    $this->postJson(rutaCopiaDelPerfil($this->activos), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origen_id'], 'errores');

    expect($this->bodega->actividades()->count())->toBe(3)
        ->and($this->activos->actividades()->count())->toBe(0);
});

// ── Tablero ──────────────────────────────────────────────────────────────

test('el tablero dice qué le falta a cada puesto, primero los incompletos ocupados', function () {
    // Activos: misión y una actividad, pero la actividad está desactivada.
    $this->activos->perfil()->create(['mision' => 'Llevar el registro de los activos fijos.']);
    PuestoActividad::create(['puesto_id' => $this->activos->id, 'descripcion' => 'Actividad desactivada.', 'orden' => 1, 'activo' => false]);
    ocuparPuestoParaCopiar($this->asistente, '0800000101');
    // Uno inactivo no se mira.
    puestoParaCopiar($this->administrativa, 'Analista de Archivo', 12, ['activo' => false]);
    $this->actingAs($this->talentoHumano, 'sanctum');

    $datos = $this->getJson(RUTA_COMPLETITUD)->assertOk()->json('datos');

    expect(collect($datos['puestos'])->map(fn ($p) => [$p['cargo']['nombre'], $p['ocupado'], $p['faltan']])->all())->toBe([
        ['Asistente de Bodega', true, ['mision', 'requisitos', 'actividades', 'competencias']],
        ['Analista de Activos Fijos', false, ['requisitos', 'actividades', 'competencias']],
        ['Analista de Bodega', false, []],
    ]);

    $bodega = collect($datos['puestos'])->firstWhere('id', $this->bodega->id);
    expect($bodega)->toMatchArray([
        'tipo'         => 'plantilla',
        'mision'       => true,
        'requisitos'   => 2,
        // Solo las activas.
        'actividades'  => 2,
        'competencias' => 2,
        'completo'     => true,
    ])
        ->and($bodega['unidad_administrativa'])->toBe(['id' => $this->administrativa->id, 'nombre' => 'Gestión Administrativa'])
        ->and($bodega['grupo_ocupacional'])->toBe(['grupo' => 'Servidor Público de grado 12', 'grado_codigo' => 'SPK12', 'nivel_competencia_sugerido' => 'medio']);

    expect($datos['resumen'])->toBe([
        'total'                => 3,
        'completos'            => 1,
        'incompletos'          => 2,
        'ocupados_incompletos' => 1,
        'sin_mision'           => 1,
        'sin_requisitos'       => 2,
        'sin_actividades'      => 2,
        'sin_competencias'     => 2,
    ]);
});

test('copiar completa el puesto en el tablero', function () {
    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->postJson(rutaCopiaDelPerfil($this->activos), ['origen_id' => $this->bodega->id])->assertOk();

    $activos = collect($this->getJson(RUTA_COMPLETITUD)->json('datos.puestos'))->firstWhere('id', $this->activos->id);
    expect($activos['completo'])->toBeTrue();
});
