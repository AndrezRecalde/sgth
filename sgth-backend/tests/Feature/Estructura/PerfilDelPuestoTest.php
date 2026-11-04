<?php

use App\Enums\Permiso;
use App\Models\Estructura\Competencia;
use App\Models\Estructura\PuestoActividad;
use App\Models\Estructura\PuestoCompetencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
| El perfil del puesto para la evaluación del desempeño: el producto, los
| conocimientos y la frecuencia de cada actividad, y las competencias del
| diccionario del Ministerio que exige el puesto (lo que va en el FOR-02).
*/

function usuarioPerfilDelPuesto(string $rol, array $permisos = []): User
{
    $r = Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    foreach ($permisos as $permiso) {
        $r->givePermissionTo(Permission::firstOrCreate(['name' => $permiso->value, 'guard_name' => 'sanctum']));
    }
    $u = User::factory()->create();
    $u->assignRole($r);

    return $u;
}

function competenciaDelDiccionario(string $tipo, string $nombre): Competencia
{
    return Competencia::where('tipo', $tipo)->where('nombre', $nombre)->firstOrFail();
}

beforeEach(function () {
    $this->puesto = puestoDePrueba(unidadDePrueba());
    $this->rutaActividades = "/api/v1/estructura/puestos/{$this->puesto->id}/actividades";
    $this->rutaCompetencias = "/api/v1/estructura/puestos/{$this->puesto->id}/competencias";

    $this->servidor = usuarioPerfilDelPuesto('servidor');
    $this->lector = usuarioPerfilDelPuesto('consulta-estructura', [Permiso::VER_ESTRUCTURA]);
    $this->talentoHumano = usuarioPerfilDelPuesto('admin-uath', [Permiso::VER_ESTRUCTURA, Permiso::GESTIONAR_PUESTOS]);
});

test('la migración carga el diccionario del libro de Talento Humano', function () {
    expect(Competencia::where('tipo', 'tecnica')->count())->toBe(34)
        ->and(Competencia::where('tipo', 'conductual')->count())->toBe(8)
        // Los dos nombres que el libro trae mal escritos.
        ->and(Competencia::where('nombre', 'Construcción de Relaciones')->exists())->toBeTrue()
        ->and(Competencia::where('nombre', 'Aprendizaje Continuo')->exists())->toBeTrue()
        ->and(Competencia::whereNull('comportamiento_bajo')->exists())->toBeFalse();
});

test('el número de cada comportamiento es el que escribe el FOR-02', function () {
    $this->actingAs($this->lector, 'sanctum');

    // Las seis competencias del FOR-02 de 2025 del ejemplo, todas en nivel alto:
    // las técnicas 40, 67 y 46 y las conductuales 1, 4 y 22.
    $esperados = [
        ['tecnica', 'Identificación de Problemas', 40],
        ['tecnica', 'Diseño de Tecnología', 67],
        ['tecnica', 'Organización de Sistemas', 46],
        ['conductual', 'Trabajo en Equipo', 1],
        ['conductual', 'Orientación de Servicio', 4],
        ['conductual', 'Aprendizaje Continuo', 22],
    ];

    $datos = collect($this->getJson('/api/v1/estructura/competencias')->assertOk()->json('datos'));

    foreach ($esperados as [$tipo, $nombre, $numero]) {
        $fila = $datos->first(fn ($c) => $c['tipo'] === $tipo && $c['nombre'] === $nombre);
        expect($fila['numeros_comportamiento']['alto'])->toBe($numero);
    }

    $analitico = competenciaDelDiccionario('tecnica', 'Pensamiento Analítico');
    expect($analitico->numeroDeComportamiento(\App\Enums\NivelCompetencia::MEDIO))->toBe(38)
        ->and($analitico->numeroDeComportamiento(\App\Enums\NivelCompetencia::BAJO))->toBe(39);
});

test('el diccionario lo lee quien ve la estructura y lo cambia quien gestiona puestos', function () {
    $competencia = competenciaDelDiccionario('conductual', 'Flexibilidad');

    $this->actingAs($this->servidor, 'sanctum')
        ->getJson('/api/v1/estructura/competencias')->assertStatus(403);

    $this->actingAs($this->lector, 'sanctum');
    $this->getJson('/api/v1/estructura/competencias?tipo=conductual')
        ->assertOk()
        ->assertJsonCount(8, 'datos');
    $this->putJson("/api/v1/estructura/competencias/{$competencia->id}", ['activo' => false])->assertStatus(403);
    $this->postJson('/api/v1/estructura/competencias', [
        'tipo' => 'conductual', 'nombre' => 'Inventada',
        'comportamiento_alto' => 'a', 'comportamiento_medio' => 'b', 'comportamiento_bajo' => 'c',
    ])->assertStatus(403);

    expect($competencia->fresh()->activo)->toBeTrue();
});

test('una competencia nueva toma el número siguiente de su tipo', function () {
    $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson('/api/v1/estructura/competencias', [
            'tipo'                 => 'conductual',
            'nombre'               => 'Liderazgo',
            'definicion'           => 'Guiar a un equipo hacia los objetivos de la institución.',
            'comportamiento_alto'  => 'Orienta a la unidad hacia objetivos estratégicos.',
            'comportamiento_medio' => 'Coordina a su equipo y reparte el trabajo.',
            'comportamiento_bajo'  => 'Asume el liderazgo de tareas puntuales.',
            'numero'               => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('datos.numero', 9)
        ->assertJsonPath('datos.numeros_comportamiento.alto', 25);

    // El nombre no se repite dentro del mismo tipo.
    $this->postJson('/api/v1/estructura/competencias', [
        'tipo' => 'conductual', 'nombre' => 'Liderazgo',
        'comportamiento_alto' => 'a', 'comportamiento_medio' => 'b', 'comportamiento_bajo' => 'c',
    ])->assertStatus(422)->assertJsonStructure(['errores' => ['nombre']]);
});

test('las actividades guardan producto, conocimientos y frecuencia', function () {
    $this->actingAs($this->talentoHumano, 'sanctum');

    $id = $this->postJson($this->rutaActividades, [
        'descripcion'   => 'Mantenimiento preventivo de servidores',
        'producto'      => 'Informe de mantenimiento',
        'conocimientos' => 'Normas de Control Interno 410-09',
        'frecuencia'    => 'mensual',
    ])
        ->assertCreated()
        ->assertJsonPath('datos.frecuencia', 'mensual')
        ->json('datos.id');

    $this->patchJson("{$this->rutaActividades}/{$id}", ['frecuencia' => 'anual', 'producto' => null])
        ->assertOk();

    $actividad = PuestoActividad::find($id);
    expect($actividad->frecuencia->value)->toBe('anual')
        ->and($actividad->producto)->toBeNull()
        ->and($actividad->conocimientos)->toBe('Normas de Control Interno 410-09');

    $this->postJson($this->rutaActividades, ['descripcion' => 'Otra', 'frecuencia' => 'cada rato'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['frecuencia']]);
});

test('una actividad sin los campos nuevos se sigue registrando como antes', function () {
    // Así las registra el Dispensario para la matriz de riesgos del FEMO.
    $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson($this->rutaActividades, ['descripcion' => 'Conducción de vehículos'])
        ->assertCreated()
        ->assertJsonPath('datos.producto', null)
        ->assertJsonPath('datos.frecuencia', null);
});

test('Talento Humano arma las competencias del puesto', function () {
    $tecnica = competenciaDelDiccionario('tecnica', 'Identificación de Problemas');
    $conductual = competenciaDelDiccionario('conductual', 'Trabajo en Equipo');

    $this->actingAs($this->talentoHumano, 'sanctum');

    $id = $this->postJson($this->rutaCompetencias, ['competencia_id' => $tecnica->id, 'nivel' => 'medio'])
        ->assertCreated()
        ->assertJsonPath('datos.numero_comportamiento', 41)
        ->assertJsonPath('datos.nivel_label', 'Medio')
        ->assertJsonPath('datos.comportamiento', $tecnica->comportamiento_medio)
        ->json('datos.id');
    $this->postJson($this->rutaCompetencias, ['competencia_id' => $conductual->id, 'nivel' => 'alto'])
        ->assertCreated();

    // Las técnicas primero.
    $this->getJson($this->rutaCompetencias)
        ->assertOk()
        ->assertJsonPath('datos.0.tipo', 'tecnica')
        ->assertJsonPath('datos.1.tipo', 'conductual');

    $this->patchJson("{$this->rutaCompetencias}/{$id}", ['nivel' => 'alto'])
        ->assertOk()
        ->assertJsonPath('datos.numero_comportamiento', 40);

    $this->deleteJson("{$this->rutaCompetencias}/{$id}")->assertOk();
    expect(PuestoCompetencia::count())->toBe(1);
});

test('el puesto no repite una competencia ni toma una retirada', function () {
    $tecnica = competenciaDelDiccionario('tecnica', 'Expresión Oral');
    $retirada = competenciaDelDiccionario('tecnica', 'Reparación');
    $retirada->update(['activo' => false]);

    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->postJson($this->rutaCompetencias, ['competencia_id' => $tecnica->id, 'nivel' => 'bajo'])->assertCreated();
    $this->postJson($this->rutaCompetencias, ['competencia_id' => $tecnica->id, 'nivel' => 'alto'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['competencia_id']]);
    $this->postJson($this->rutaCompetencias, ['competencia_id' => $retirada->id, 'nivel' => 'alto'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['competencia_id']]);
    $this->postJson($this->rutaCompetencias, ['competencia_id' => $tecnica->id, 'nivel' => 'altísimo'])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['nivel']]);
});

test('una competencia que un puesto exige no se elimina, se retira', function () {
    $competencia = competenciaDelDiccionario('conductual', 'Iniciativa');
    PuestoCompetencia::create(['puesto_id' => $this->puesto->id, 'competencia_id' => $competencia->id, 'nivel' => 'medio']);

    $this->actingAs($this->talentoHumano, 'sanctum');

    $this->deleteJson("/api/v1/estructura/competencias/{$competencia->id}")->assertStatus(422);
    $this->putJson("/api/v1/estructura/competencias/{$competencia->id}", ['activo' => false])->assertOk();

    // Retirada, el puesto la conserva y la pantalla lo sabe.
    $this->getJson($this->rutaCompetencias)
        ->assertOk()
        ->assertJsonPath('datos.0.competencia_activa', false);
    $this->getJson('/api/v1/estructura/competencias?solo_activas=1&tipo=conductual')
        ->assertOk()
        ->assertJsonCount(7, 'datos');
    // Así lo manda axios desde la pantalla.
    $this->getJson('/api/v1/estructura/competencias?solo_activas=true&tipo=conductual')
        ->assertOk()
        ->assertJsonCount(7, 'datos');
});

test('las competencias del puesto piden ver la estructura y gestionar puestos', function () {
    $competencia = competenciaDelDiccionario('tecnica', 'Instrucción');

    $this->actingAs($this->servidor, 'sanctum')->getJson($this->rutaCompetencias)->assertStatus(403);
    // El médico lee las actividades para el FEMO, pero no las competencias.
    $this->actingAs(usuarioPerfilDelPuesto('medico'), 'sanctum')->getJson($this->rutaCompetencias)->assertStatus(403);

    $this->actingAs($this->lector, 'sanctum');
    $this->getJson($this->rutaCompetencias)->assertOk();
    $this->postJson($this->rutaCompetencias, ['competencia_id' => $competencia->id, 'nivel' => 'alto'])->assertStatus(403);

    expect(PuestoCompetencia::count())->toBe(0);
});

test('borrar el puesto se lleva sus competencias', function () {
    PuestoCompetencia::create([
        'puesto_id'      => $this->puesto->id,
        'competencia_id' => competenciaDelDiccionario('tecnica', 'Instalación')->id,
        'nivel'          => 'bajo',
    ]);

    $this->puesto->forceDelete();

    expect(PuestoCompetencia::count())->toBe(0);
});
