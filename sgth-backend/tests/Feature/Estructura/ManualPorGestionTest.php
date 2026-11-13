<?php

namespace Tests\Feature\Estructura;

use App\Enums\Permiso;
use App\Models\Estructura\GrupoOcupacional;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\User;
use App\Services\Estructura\ManualPorGestionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
| El Manual de Puestos de una gestión en PDF (docs/diseno-manual-de-puestos.md,
| P5; PR 14): portada, distribución de los puestos por subproceso y la ficha de
| cada uno. Sin el diagrama de Calidad (v1.6).
*/

function rutaManualDeGestion(UnidadAdministrativa $unidad): string
{
    return "/api/v1/estructura/unidades-administrativas/{$unidad->id}/manual-de-puestos";
}

function puestoDelManual(UnidadAdministrativa $unidad, string $cargo, int $grado, array $atributos = []): Puesto
{
    $grupo = GrupoOcupacional::firstOrCreate(['grado_codigo' => "SPM{$grado}"], [
        'grado_numerico'        => $grado,
        'grupo'                 => "Servidor Público {$grado}",
        'denominacion_generica' => 'Profesional',
        'rmu'                   => 1000 + $grado,
        'regimen'               => 'losep',
        'activo'                => true,
    ]);

    return puestoDePrueba($unidad, $cargo, ['grupo_ocupacional_id' => $grupo->id, ...$atributos]);
}

beforeEach(function () {
    $rol = Role::firstOrCreate(['name' => 'lector-manual-prueba', 'guard_name' => 'sanctum']);
    $rol->givePermissionTo(Permission::firstOrCreate(['name' => Permiso::VER_ESTRUCTURA->value, 'guard_name' => 'sanctum']));
    $this->lector = User::factory()->create();
    $this->lector->assignRole($rol);

    $this->institucion    = unidadDePrueba(['nombre' => 'GADPE', 'nivel' => 1]);
    $this->administrativa = unidadDePrueba(['nombre' => 'Gestión Administrativa', 'nivel' => 2, 'unidad_padre_id' => $this->institucion->id]);
    $this->guardalmacen   = unidadDePrueba(['nombre' => 'Guardalmacén', 'nivel' => 3, 'unidad_padre_id' => $this->administrativa->id]);
    $this->financiera     = unidadDePrueba(['nombre' => 'Gestión Financiera', 'nivel' => 2, 'unidad_padre_id' => $this->institucion->id]);

    // En la gestión, sin subproceso: el director y un analista.
    $this->analista = puestoDelManual($this->administrativa, 'Analista Administrativo', 11, ['plazas' => 2]);
    $this->director = puestoDelManual($this->administrativa, 'Director Administrativo', 3, ['es_jefe' => true]);
    // El subproceso lo dice el perfil (D2)…
    $this->bodega = puestoDelManual($this->administrativa, 'Analista de Bodega', 11);
    $this->bodega->perfil()->create(['mision' => 'Administrar la bodega.', 'subproceso' => 'Activos Fijos y Bodega']);
    $this->asistenteBodega = puestoDelManual($this->administrativa, 'Asistente de Bodega', 7, ['plazas' => 3]);
    $this->asistenteBodega->perfil()->create(['subproceso' => 'Activos Fijos y Bodega ']);
    // …o la unidad hija en que quedó el puesto.
    $this->guarda = puestoDelManual($this->guardalmacen, 'Guardalmacén', 9);

    // No entran: de contrato, inactivo y de otra gestión.
    puestoDelManual($this->administrativa, 'Técnico ocasional', 7, ['tipo' => 'contrato', 'plazas' => null]);
    puestoDelManual($this->administrativa, 'Analista retirado', 11, ['activo' => false]);
    puestoDelManual($this->financiera, 'Analista Financiero', 11);
});

test('el manual reparte los puestos de plantilla de la gestión por subproceso', function () {
    $datos = app(ManualPorGestionPdfService::class)->datos($this->administrativa);

    expect($datos['gestion'])->toBe('Gestión Administrativa')
        ->and(collect($datos['distribucion'])->map(fn ($g) => [
            $g['subproceso'],
            collect($g['puestos'])->map(fn ($p) => [$p['denominacion'], $p['grado'], $p['plazas']])->all(),
            $g['plazas'],
        ])->all())->toBe([
            // Primero la gestión: la jefatura y después de mayor a menor grado.
            [null, [['Director Administrativo', 'SPM3', 1], ['Analista Administrativo', 'SPM11', 2]], 3],
            // Los subprocesos por nombre; el espacio de más no abre otro.
            ['Activos Fijos y Bodega', [['Analista de Bodega', 'SPM11', 1], ['Asistente de Bodega', 'SPM7', 3]], 4],
            ['Guardalmacén', [['Guardalmacén', 'SPM9', 1]], 1],
        ])
        ->and($datos['total_puestos'])->toBe(5)
        ->and($datos['total_plazas'])->toBe(8);

    // Las fichas, en el orden de la distribución y con su contenido.
    expect(collect($datos['fichas'])->map(fn ($f) => $f['identificacion']['Denominación del puesto'])->all())->toBe([
        'Director Administrativo', 'Analista Administrativo', 'Analista de Bodega', 'Asistente de Bodega', 'Guardalmacén',
    ])
        ->and($datos['fichas'][2]['mision'])->toBe('Administrar la bodega.')
        ->and($datos['fichas'][2]['identificacion']['Unidad administrativa'])->toBe('Gestión Administrativa / Activos Fijos y Bodega');
});

test('el manual lo descarga quien ve la estructura, y es un PDF', function () {
    $url = rutaManualDeGestion($this->administrativa);

    $respuesta = $this->actingAs($this->lector, 'sanctum')->get($url)->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('manual_de_puestos_gestion_administrativa.pdf')
        ->and(substr($respuesta->getContent(), 0, 4))->toBe('%PDF');

    $this->actingAs(User::factory()->create(), 'sanctum')->get($url)->assertForbidden();
});

test('el manual es de una gestión, y de una que tenga puestos de plantilla', function () {
    $this->actingAs($this->lector, 'sanctum');

    $this->getJson(rutaManualDeGestion($this->institucion))
        ->assertUnprocessable()
        ->assertJsonPath('errores.unidad.0', 'El manual se imprime por gestión: elija una unidad del segundo nivel del organigrama.');
    $this->getJson(rutaManualDeGestion($this->guardalmacen))->assertUnprocessable();

    $vacia = unidadDePrueba(['nombre' => 'Gestión Ambiental', 'nivel' => 2, 'unidad_padre_id' => $this->institucion->id]);
    puestoDelManual($vacia, 'Técnico ocasional', 7, ['tipo' => 'contrato', 'plazas' => null]);
    $this->getJson(rutaManualDeGestion($vacia))
        ->assertUnprocessable()
        ->assertJsonPath('errores.unidad.0', 'Gestión Ambiental no tiene puestos de plantilla activos.');

    $this->getJson('/api/v1/estructura/unidades-administrativas/999999/manual-de-puestos')->assertNotFound();
});
