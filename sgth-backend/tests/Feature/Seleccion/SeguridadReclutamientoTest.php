<?php

namespace Tests\Feature\Seleccion;

use App\Models\Estructura\Cargo;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Seleccion\Convocatoria;
use App\Models\Seleccion\DocumentoPostulante;
use App\Models\Seleccion\Postulante;
use App\Models\User;
use Database\Seeders\ContenedorExpressSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Auditoría de Reclutamiento (2026-10-04), lo que no esperaba decisión de
 * Talento Humano: documentos en el disco privado, la cédula de diez dígitos y
 * los topes de paginación.
 */

beforeEach(function () {
    // Los roles con sus permisos reales: las rutas exigen el permiso desde el 2026-10-05.
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    Storage::fake('local');
    Storage::fake('public');
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->user = User::factory()->create();
    $this->user->assignRole('admin-uath');
    $this->actingAs($this->user, 'sanctum');

    $this->seed(ContenedorExpressSeeder::class);

    $unidad = UnidadAdministrativa::create(['codigo' => 'SEG-REC', 'nombre' => 'Unidad Seguridad Rec', 'nivel' => 1]);
    $cargo  = Cargo::create(['nombre' => 'Analista Seguridad']);
    $this->puesto = Puesto::create([
        'unidad_administrativa_id' => $unidad->id, 'cargo_id' => $cargo->id, 'plazas' => 5, 'rmu' => 1000,
    ]);
    $this->contenedor = Convocatoria::where('codigo', 'EXP-PROFESIONALES')->firstOrFail();
    $this->base = "/api/v1/seleccion/convocatorias/{$this->contenedor->id}/postulantes";

    $this->inscribir = fn (string $cedula) => $this->postJson($this->base, [
        'puesto_id' => $this->puesto->id,
        'cedula'    => $cedula,
        'nombres'   => 'Aspirante',
        'apellidos' => 'Seguridad',
        'correo'    => 'aspirante@test.ec',
        'genero'    => 'femenino',
    ]);
});

test('el documento del postulante va al disco privado y se baja por el API', function () {
    $id = ($this->inscribir)('1712345678')->assertCreated()->json('datos.id');

    $respuesta = $this->post("{$this->base}/{$id}/documentos", [
        'tipo'    => 'cedula',
        'archivo' => UploadedFile::fake()->create('cedula.pdf', 50, 'application/pdf'),
    ])->assertCreated()
        ->assertJsonMissingPath('datos.ruta');

    $documento = DocumentoPostulante::findOrFail($respuesta->json('datos.id'));

    // Antes iba al disco `public`, enlazado en public/storage: se abría sin
    // iniciar sesión.
    Storage::disk('local')->assertExists($documento->ruta);
    Storage::disk('public')->assertMissing($documento->ruta);
    expect($documento->extension)->toBe('pdf');

    $this->get("{$this->base}/{$id}/documentos/{$documento->id}")
        ->assertOk()
        ->assertDownload('cedula.pdf');
});

test('el documento solo se baja por su propio postulante', function () {
    $id   = ($this->inscribir)('1712345678')->json('datos.id');
    $otro = ($this->inscribir)('1712345679')->json('datos.id');

    $docId = $this->post("{$this->base}/{$id}/documentos", [
        'tipo' => 'cv', 'archivo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
    ])->json('datos.id');

    $this->get("{$this->base}/{$otro}/documentos/{$docId}")->assertNotFound();
});

test('borrar un postulante no borra los archivos de sus documentos', function () {
    $id = ($this->inscribir)('1712345678')->json('datos.id');
    $docId = $this->post("{$this->base}/{$id}/documentos", [
        'tipo' => 'cv', 'archivo' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
    ])->json('datos.id');

    $this->deleteJson("{$this->base}/{$id}")->assertOk();

    // Es un borrado lógico: el documento sigue en la base y su archivo también.
    Storage::disk('local')->assertExists(DocumentoPostulante::findOrFail($docId)->ruta);
    expect(Postulante::withTrashed()->find($id)->trashed())->toBeTrue();
});

test('la cédula tiene que tener diez dígitos', function () {
    // Antes `max:20` contra una columna varchar(10): con 11 caracteres, 500.
    ($this->inscribir)('17123456789')
        ->assertUnprocessable()
        ->assertJsonPath('errores.cedula.0', 'La cédula debe tener 10 dígitos numéricos.');

    ($this->inscribir)('ABC1234567')->assertUnprocessable();
    ($this->inscribir)('1712345678')->assertCreated();
});

test('los listados no devuelven más de cien filas por página', function () {
    $this->getJson('/api/v1/seleccion/convocatorias?per_page=100000')
        ->assertOk()
        ->assertJsonPath('datos.per_page', 100);

    $this->getJson("/api/v1/seleccion/express/{$this->contenedor->id}/aspirantes?per_page=100000")
        ->assertOk()
        ->assertJsonPath('datos.per_page', 100);
});

test('aplicar una plantilla a una convocatoria inexistente da 404, no 500', function () {
    $plantilla = \App\Models\Seleccion\PlantillaEvaluacion::create([
        'nombre' => 'Plantilla seguridad', 'tipo_contrato' => 'losep', 'activa' => true,
    ]);

    $this->postJson("/api/v1/seleccion/plantillas/{$plantilla->id}/aplicar/999999")
        ->assertNotFound();
});
