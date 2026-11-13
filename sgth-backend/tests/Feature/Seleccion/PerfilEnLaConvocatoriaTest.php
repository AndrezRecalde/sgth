<?php

use App\Models\Estructura\Competencia;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\CriterioEvaluacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El perfil del puesto en la convocatoria (docs/diseno-manual-de-puestos.md,
| P7; PR 16): el vigente mientras es un borrador, y el guardado al publicarla.
*/

function convocatoriaConPerfil(Puesto $puesto, array $atributos = []): Convocatoria
{
    static $n = 0;
    $n++;

    return Convocatoria::create([
        'puesto_id' => $puesto->id, 'codigo' => "CNV-PERFIL-{$n}",
        'titulo' => 'Analista de Bodega', 'descripcion' => 'Concurso de méritos y oposición.',
        'tipo' => 'externa', 'vacantes' => 1,
        'fecha_inicio' => now(), 'fecha_fin' => now()->addDays(10),
        'estado' => 'borrador', 'tipo_proceso' => 'formal',
        ...$atributos,
    ]);
}

/** Criterios que suman 100, para poder publicarla. */
function criteriosParaPublicar(Convocatoria $convocatoria): void
{
    CriterioEvaluacion::create([
        'convocatoria_id' => $convocatoria->id, 'seccion' => 'meritos',
        'nombre' => 'Instrucción formal', 'puntaje_maximo' => 100,
        'tipo_input' => 'numero', 'orden' => 1, 'activo' => true,
    ]);
}

function perfilDelPuestoConvocado(Puesto $puesto): void
{
    $perfil = $puesto->perfil()->create([
        'mision'                    => 'Administrar la bodega y los activos de la institución.',
        'area_conocimiento'         => 'Administración',
        'especificidad_experiencia' => 'Manejo de inventarios.',
        'capacitacion'              => 'Control de bienes del sector público.',
    ]);
    $perfil->requisitos()->create(['nivel_instruccion' => 'tercer_nivel', 'detalle' => 'Administración', 'meses_experiencia' => 24, 'orden' => 1]);

    PuestoActividad::create(['puesto_id' => $puesto->id, 'descripcion' => 'Controla el ingreso y egreso de los bienes.', 'orden' => 1, 'activo' => true]);
    PuestoActividad::create(['puesto_id' => $puesto->id, 'descripcion' => 'Una que ya no hace.', 'orden' => 2, 'activo' => false]);

    // La conductual primero, para ver que salen las técnicas antes.
    PuestoCompetencia::create(['puesto_id' => $puesto->id, 'competencia_id' => Competencia::where('nombre', 'Trabajo en Equipo')->value('id'), 'nivel' => 'alto']);
    PuestoCompetencia::create(['puesto_id' => $puesto->id, 'competencia_id' => Competencia::where('nombre', 'Pensamiento Analítico')->value('id'), 'nivel' => 'medio']);
}

function verConvocatoria(Convocatoria $convocatoria): array
{
    return test()->getJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}")->assertOk()->json('datos');
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');

    $this->puesto = puestoDePrueba(unidadDePrueba(['nombre' => 'Gestión Administrativa']), 'Analista de Bodega');
    perfilDelPuestoConvocado($this->puesto);

    $this->actingAs($this->user, 'sanctum');
});

test('en borrador, la convocatoria muestra el perfil vigente del puesto', function () {
    $convocatoria = convocatoriaConPerfil($this->puesto);

    $perfil = verConvocatoria($convocatoria)['perfil_del_puesto'];
    expect($perfil)->toMatchArray([
        'fuente'                    => 'puesto',
        'tomado_en'                 => null,
        'mision'                    => 'Administrar la bodega y los activos de la institución.',
        'area_conocimiento'         => 'Administración',
        'especificidad_experiencia' => 'Manejo de inventarios.',
        'capacitacion'              => 'Control de bienes del sector público.',
        // Solo las actividades activas.
        'actividades'               => ['Controla el ingreso y egreso de los bienes.'],
        'faltan'                    => [],
    ])
        ->and($perfil['requisitos'])->toBe([[
            'nivel_instruccion' => 'tercer_nivel',
            'instruccion'       => 'Tercer nivel de grado',
            'detalle'           => 'Administración',
            'meses_experiencia' => 24,
        ]])
        ->and(collect($perfil['competencias'])->map(fn ($c) => [$c['nombre'], $c['tipo'], $c['nivel']])->all())->toBe([
            ['Pensamiento Analítico', 'tecnica', 'medio'],
            ['Trabajo en Equipo', 'conductual', 'alto'],
        ])
        ->and($perfil['competencias'][0]['comportamiento'])->not->toBeEmpty();

    // Si TH corrige el perfil, el borrador lo ve.
    $this->puesto->perfil->update(['mision' => 'Misión corregida.']);
    $this->puesto->competencias()->delete();

    $perfil = verConvocatoria($convocatoria)['perfil_del_puesto'];
    expect($perfil['mision'])->toBe('Misión corregida.')
        ->and($perfil['faltan'])->toBe(['competencias']);
});

test('al publicarla, el perfil se guarda en sus bases y ya no cambia con el puesto', function () {
    $convocatoria = convocatoriaConPerfil($this->puesto);
    criteriosParaPublicar($convocatoria);

    $this->patchJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/publicar")->assertOk();

    $bases = $convocatoria->fresh()->bases_concurso;
    expect($bases['perfil']['mision'])->toBe('Administrar la bodega y los activos de la institución.')
        ->and($bases['perfil']['tomado_en'])->not->toBeNull()
        ->and($bases['perfil'])->not->toHaveKey('fuente');

    // Después de publicada, editar el puesto no cambia lo que se anunció.
    $this->puesto->perfil->update(['mision' => 'Otra misión.']);
    $this->puesto->perfil->requisitos()->delete();

    $perfil = verConvocatoria($convocatoria)['perfil_del_puesto'];
    expect($perfil)->toMatchArray([
        'fuente' => 'convocatoria',
        'mision' => 'Administrar la bodega y los activos de la institución.',
    ])
        ->and($perfil['tomado_en'])->toBe($bases['perfil']['tomado_en'])
        ->and($perfil['requisitos'])->toHaveCount(1);
});

test('publicar conserva lo demás de las bases', function () {
    $convocatoria = convocatoriaConPerfil($this->puesto, ['bases_concurso' => ['lugar' => 'Prefectura de Esmeraldas']]);
    criteriosParaPublicar($convocatoria);

    $this->patchJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/publicar")->assertOk();

    expect($convocatoria->fresh()->bases_concurso)
        ->toHaveKey('lugar', 'Prefectura de Esmeraldas')
        ->toHaveKey('perfil');
});

test('en borrador, un perfil que llegue en las bases no reemplaza el del puesto', function () {
    $convocatoria = convocatoriaConPerfil($this->puesto);
    $this->patchJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}", [
        'bases_concurso' => ['perfil' => ['mision' => 'Escrita a mano.']],
    ])->assertOk();

    expect(verConvocatoria($convocatoria)['perfil_del_puesto'])
        ->toMatchArray(['fuente' => 'puesto', 'mision' => 'Administrar la bodega y los activos de la institución.']);

    // Y al publicar, se guarda el del puesto.
    criteriosParaPublicar($convocatoria);
    $this->patchJson("/api/v1/seleccion/convocatorias/{$convocatoria->id}/publicar")->assertOk();
    expect($convocatoria->fresh()->bases_concurso['perfil']['mision'])->toBe('Administrar la bodega y los activos de la institución.');
});

test('una convocatoria publicada sin perfil guardado muestra el vigente', function () {
    // Las que se publicaron antes de este cambio.
    $convocatoria = convocatoriaConPerfil($this->puesto, ['estado' => 'publicada']);

    expect(verConvocatoria($convocatoria)['perfil_del_puesto'])
        ->toMatchArray(['fuente' => 'puesto', 'tomado_en' => null]);
});

test('los contenedores de Reclutamiento Express, sin puesto, no tienen perfil', function () {
    $this->seed(\Database\Seeders\ContenedorExpressSeeder::class);
    $contenedor = Convocatoria::where('es_contenedor_permanente', true)->firstOrFail();

    expect(verConvocatoria($contenedor)['perfil_del_puesto'])->toBeNull();
});
