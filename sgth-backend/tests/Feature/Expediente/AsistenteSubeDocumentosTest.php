<?php

use App\Models\Expediente\DocumentoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Desde el 2026-10-03 el asistente de Talento Humano sube documentos al
 * expediente: veía el botón y recibía 403, aunque en Declaraciones sí podía
 * subir. Decisión de TH «por el momento»; borrar sigue siendo de admin-uath.
 */

beforeEach(function () {
    Storage::fake('local');
    foreach (['admin-uath', 'asistente-uath', 'servidor'] as $rol) {
        Role::firstOrCreate(['name' => $rol, 'guard_name' => 'sanctum']);
    }

    $unidad = unidadDePrueba(['nombre' => 'Unidad Asistente Docs']);
    $this->servidor = Servidor::forceCreate([
        'cedula' => '0809999991', 'nombre' => 'Tomás', 'apellido' => 'Archivo',
        'puesto_id' => puestoDePrueba($unidad, 'Puesto Asistente Docs')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);
    $this->url = "/api/v1/expediente/servidores/{$this->servidor->id}/documentos";

    $this->asistente = User::factory()->create();
    $this->asistente->assignRole('asistente-uath');
});

function archivoAsistenteSubeDocumentos(): array
{
    return [
        'tipo_documento' => 'cedula_identidad',
        'archivo'        => UploadedFile::fake()->create('cedula.pdf', 20, 'application/pdf'),
    ];
}

test('el asistente sube un documento al expediente', function () {
    $this->actingAs($this->asistente, 'sanctum')
        ->post($this->url, archivoAsistenteSubeDocumentos(), ['Accept' => 'application/json'])
        ->assertCreated();

    expect(DocumentoServidor::where('servidor_id', $this->servidor->id)->count())->toBe(1);
});

test('el asistente no borra documentos; admin-uath sí', function () {
    $uath = User::factory()->create();
    $uath->assignRole('admin-uath');

    $id = $this->actingAs($uath, 'sanctum')
        ->post($this->url, archivoAsistenteSubeDocumentos(), ['Accept' => 'application/json'])
        ->json('datos.id');

    $this->actingAs($this->asistente, 'sanctum')->deleteJson("{$this->url}/{$id}")->assertForbidden();
    $this->actingAs($uath, 'sanctum')->deleteJson("{$this->url}/{$id}")->assertOk();
});

test('el titular sigue sin poder subir a su propio expediente', function () {
    $titular = User::factory()->create(['servidor_id' => $this->servidor->id]);
    $titular->assignRole('servidor');

    $this->actingAs($titular, 'sanctum')
        ->post($this->url, archivoAsistenteSubeDocumentos(), ['Accept' => 'application/json'])
        ->assertForbidden();
});
