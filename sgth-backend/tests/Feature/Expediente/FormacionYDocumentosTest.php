<?php

use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Arreglos pequeños de las pestañas Formación y Documentos (2026-10-03).
 */

beforeEach(function () {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $unidad = unidadDePrueba(['nombre' => 'Unidad Formación']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0800909091', 'nombre' => 'Tere', 'apellido' => 'Formación',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Formación')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->base = "/api/v1/expediente/servidores/{$this->servidor->id}";
    $this->pdf = fn () => UploadedFile::fake()->create('declaracion.pdf', 20, 'application/pdf');
});

test('un curso de un solo día se registra', function () {
    $this->postJson("{$this->base}/historial-academico", [
        'tipo_estudio' => 'capacitacion', 'nacionalidad_estudio' => 'nacional',
        'institucion' => 'Contraloría General del Estado', 'titulo_capacitacion' => 'Control interno',
        'fecha_inicio' => '2026-05-10', 'fecha_fin' => '2026-05-10',
    ])->assertCreated();
});

test('ver el PDF de una declaración sin documento responde 404, no 422', function () {
    $declaracion = DeclaracionJuramentada::create([
        'servidor_id' => $this->servidor->id, 'fecha_declaracion' => '2026-01-10',
        'codigo_barras' => 'SIN-PDF', 'tipo_declaracion' => 'periodica',
    ]);

    $this->getJson("{$this->base}/declaraciones-juramentadas/{$declaracion->id}/documento")
        ->assertNotFound();
});

test('una declaración no repite código de barras ni lleva fecha futura', function () {
    $datos = ['fecha_declaracion' => '2026-01-10', 'codigo_barras' => 'CB-001', 'tipo_declaracion' => 'periodica'];
    $id = $this->postJson("{$this->base}/declaraciones-juramentadas", $datos)->assertCreated()->json('datos.id');

    $this->postJson("{$this->base}/declaraciones-juramentadas", $datos)
        ->assertUnprocessable()
        ->assertJsonPath('errores.codigo_barras.0', 'Ese código de barras ya está registrado en este expediente.');

    // Editarse a sí misma con su propio código, sí.
    $this->post("{$this->base}/declaraciones-juramentadas/{$id}", [...$datos, '_method' => 'PUT'],
        ['Accept' => 'application/json'])->assertOk();

    $this->postJson("{$this->base}/declaraciones-juramentadas", [
        ...$datos, 'codigo_barras' => 'CB-002', 'fecha_declaracion' => now()->addDay()->toDateString(),
    ])->assertUnprocessable()->assertJsonStructure(['errores' => ['fecha_declaracion']]);
});

test('dos PDF con el mismo nombre no se pisan, y borrar no se lleva el archivo', function () {
    $subir = fn (string $codigo) => $this->post("{$this->base}/declaraciones-juramentadas", [
        'fecha_declaracion' => '2026-01-10', 'codigo_barras' => $codigo,
        'tipo_declaracion' => 'periodica', 'documento' => ($this->pdf)(),
    ], ['Accept' => 'application/json'])->assertCreated()->json('datos.id');

    $a = DeclaracionJuramentada::find($subir('CB-A'));
    $b = DeclaracionJuramentada::find($subir('CB-B'));

    expect($a->documento_ruta)->not->toBe($b->documento_ruta)
        ->and($a->documento_nombre_archivo)->toBe('declaracion.pdf');

    $this->deleteJson("{$this->base}/declaraciones-juramentadas/{$a->id}")->assertOk();
    Storage::disk('local')->assertExists([$a->documento_ruta, $b->documento_ruta]);
});

test('reemplazar el PDF guarda el nuevo y borra el anterior', function () {
    $id = $this->post("{$this->base}/declaraciones-juramentadas", [
        'fecha_declaracion' => '2026-01-10', 'codigo_barras' => 'CB-R',
        'tipo_declaracion' => 'periodica', 'documento' => ($this->pdf)(),
    ], ['Accept' => 'application/json'])->json('datos.id');
    $anterior = DeclaracionJuramentada::find($id)->documento_ruta;

    $this->post("{$this->base}/declaraciones-juramentadas/{$id}", [
        '_method' => 'PUT', 'fecha_declaracion' => '2026-01-10', 'codigo_barras' => 'CB-R',
        'tipo_declaracion' => 'periodica', 'documento' => ($this->pdf)(),
    ], ['Accept' => 'application/json'])->assertOk();

    $nueva = DeclaracionJuramentada::find($id)->documento_ruta;
    expect($nueva)->not->toBe($anterior);
    Storage::disk('local')->assertExists($nueva);
    Storage::disk('local')->assertMissing($anterior);
});
