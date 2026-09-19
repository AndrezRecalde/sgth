<?php

namespace Tests\Feature\Expediente;

use App\Models\Expediente\DeclaracionJuramentada;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
| Lo que usa la pestaña Declaraciones del expediente desde el 2026-09-19:
| la edición con el PDF adjunto va por POST con _method=PUT (PHP no lee el
| cuerpo multipart de un PUT), y la exportación exige rango y formato.
*/

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);

    $unidad = unidadDePrueba(['codigo' => 'UATH', 'nombre' => 'Talento Humano']);
    $this->servidor = Servidor::create([
        'cedula' => '1111111111', 'nombre' => 'Ana', 'apellido' => 'Prueba',
        'regimen_laboral' => 'losep', 'estado' => true,
        'puesto_id' => puestoDePrueba($unidad)->id, 'unidad_administrativa_id' => $unidad->id,
    ]);

    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');
    $this->actingAs($uath, 'sanctum');

    $this->base = "/api/v1/expediente/servidores/{$this->servidor->id}/declaraciones-juramentadas";
});

test('la edición con _method=PUT guarda los datos y reemplaza el PDF', function () {
    $declaracion = DeclaracionJuramentada::create([
        'servidor_id' => $this->servidor->id, 'fecha_declaracion' => '2026-01-10',
        'codigo_barras' => 'ANTES', 'tipo_declaracion' => 'inicio_gestion',
    ]);

    $this->post("{$this->base}/{$declaracion->id}", [
        '_method'           => 'PUT',
        'fecha_declaracion' => '2026-02-15',
        'codigo_barras'     => 'DESPUES',
        'tipo_declaracion'  => 'periodica',
        'documento'         => UploadedFile::fake()->create('declaracion.pdf', 20, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertOk();

    $fresca = $declaracion->fresh();
    expect($fresca->codigo_barras)->toBe('DESPUES')
        ->and($fresca->documento_nombre_archivo)->toBe('declaracion.pdf');
    Storage::disk('local')->assertExists($fresca->documento_ruta);

    $this->get("{$this->base}/{$declaracion->id}/documento")->assertOk();
});

test('exportar sin rango ni formato es un 422, no un archivo', function () {
    $this->getJson("{$this->base}/exportar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin', 'formato'], 'errores');
});

test('exportar un rango con declaraciones entrega el archivo', function () {
    DeclaracionJuramentada::create([
        'servidor_id' => $this->servidor->id, 'fecha_declaracion' => '2026-03-01',
        'codigo_barras' => 'X-1', 'tipo_declaracion' => 'periodica',
    ]);

    $this->get("{$this->base}/exportar?fecha_inicio=2026-01-01&fecha_fin=2026-12-31&formato=txt")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
});
