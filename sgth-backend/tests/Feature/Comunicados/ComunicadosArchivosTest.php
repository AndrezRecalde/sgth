<?php

use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoAdjunto;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
| Los PDF de una publicación (docs/diseno-noticias-comunicados.md, regla 6):
| van al disco privado, se sirven por controlador y solo se cambian en un
| borrador. Un duplicado se lleva su copia de cada archivo. No hay portada
| (decisión del 2026-10-18).
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function pdfDePrueba(string $nombre = 'Circular-045.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

/**
 * Un archivo de verdad, no el falso de Laravel: ese informa el tipo por el
 * nombre, y lo que se prueba aquí es que se mire el contenido, como pasa con
 * una subida real.
 */
function archivoRealDePrueba(string $nombre, string $contenido): UploadedFile
{
    $ruta = tempnam(sys_get_temp_dir(), 'comunicado');
    file_put_contents($ruta, $contenido);

    return new UploadedFile($ruta, $nombre, null, null, true);
}

function subirPdfDePrueba(object $prueba, Comunicado $comunicado, ?UploadedFile $archivo = null)
{
    return $prueba->actingAs($prueba->autor, 'sanctum')->post(
        "/api/v1/comunicados/{$comunicado->id}/adjuntos",
        ['archivo' => $archivo ?? pdfDePrueba()],
        ['Accept' => 'application/json'],
    );
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->seed(RolPermisoSeeder::class);
    $this->autor = usuarioDeComunicados('asistente-uath');
    $this->admin = usuarioDeComunicados('admin-uath');
    servidorDeComunicados(unidadDeComunicados('Dirección de Talento Humano'));
    $this->borrador = borradorDeComunicado($this->autor);
});

test('los PDF van al disco privado, en orden, y se descargan con su nombre', function () {
    foreach (['Circular-045.pdf', '../../Formato de turnos.pdf'] as $nombre) {
        subirPdfDePrueba($this, $this->borrador, pdfDePrueba($nombre))->assertCreated();
    }

    $adjuntos = $this->borrador->adjuntos()->get();
    expect($adjuntos->pluck('nombre_original')->all())->toBe(['Circular-045.pdf', 'Formato de turnos.pdf'])
        ->and($adjuntos->pluck('orden')->all())->toBe([1, 2]);

    $adjunto = $adjuntos->first();
    expect($adjunto->path)->toStartWith("comunicados/{$this->borrador->id}/adjuntos/")
        ->and(Storage::disk('public')->allFiles())->toBe([]);
    Storage::disk('local')->assertExists($adjunto->path);

    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/comunicados/{$this->borrador->id}/adjuntos/{$adjunto->id}")
        ->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('Circular-045.pdf');

    $this->actingAs($this->autor, 'sanctum')
        ->getJson("/api/v1/comunicados/{$this->borrador->id}")
        ->assertJsonCount(2, 'datos.adjuntos')
        ->assertJsonPath('datos.adjuntos.0.nombre', 'Circular-045.pdf')
        ->assertJsonMissingPath('datos.adjuntos.0.path');
});

test('un archivo que no es PDF no entra aunque se llame .pdf', function () {
    subirPdfDePrueba($this, $this->borrador, archivoRealDePrueba('falso.pdf', '<html><body><script>alert(1)</script></body></html>'))
        ->assertStatus(422)
        ->assertJsonPath('errores.archivo.0', 'El documento debe ser un PDF.');

    // Y uno que sí lo es, leído igual por su contenido, entra.
    subirPdfDePrueba($this, $this->borrador, archivoRealDePrueba(
        'circular.pdf',
        "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n",
    ))->assertCreated();
});

test('hasta cinco documentos por publicación', function () {
    foreach (range(1, 5) as $i) {
        subirPdfDePrueba($this, $this->borrador, pdfDePrueba("doc-{$i}.pdf"))->assertCreated();
    }

    subirPdfDePrueba($this, $this->borrador, pdfDePrueba('sexto.pdf'))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Una publicación lleva hasta 5 documentos adjuntos: quite uno antes de agregar otro.');

    // El sexto no dejó su archivo en el disco.
    expect(Storage::disk('local')->allFiles("comunicados/{$this->borrador->id}/adjuntos"))->toHaveCount(5);
});

test('quitar un documento lo borra del disco; el de otra publicación no se alcanza', function () {
    subirPdfDePrueba($this, $this->borrador)->assertCreated();
    $adjunto = $this->borrador->adjuntos()->first();

    $otro = borradorDeComunicado($this->autor);
    $this->actingAs($this->autor, 'sanctum')
        ->deleteJson("/api/v1/comunicados/{$otro->id}/adjuntos/{$adjunto->id}")
        ->assertNotFound();
    $this->actingAs($this->autor, 'sanctum')
        ->get("/api/v1/comunicados/{$otro->id}/adjuntos/{$adjunto->id}")
        ->assertNotFound();

    $this->actingAs($this->autor, 'sanctum')
        ->deleteJson("/api/v1/comunicados/{$this->borrador->id}/adjuntos/{$adjunto->id}")
        ->assertOk();

    expect(ComunicadoAdjunto::find($adjunto->id))->toBeNull();
    Storage::disk('local')->assertMissing($adjunto->path);
});

test('en lo publicado los documentos ya no cambian', function () {
    subirPdfDePrueba($this, $this->borrador)->assertCreated();
    $adjunto = $this->borrador->adjuntos()->first();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/comunicados/{$this->borrador->id}/publicar")
        ->assertOk();

    subirPdfDePrueba($this, $this->borrador)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Los documentos solo se cambian en un borrador. Para corregir lo publicado, retírelo y duplíquelo.');
    $this->actingAs($this->autor, 'sanctum')
        ->deleteJson("/api/v1/comunicados/{$this->borrador->id}/adjuntos/{$adjunto->id}")
        ->assertStatus(422);

    Storage::disk('local')->assertExists($adjunto->path);
    // Lo rechazado no dejó archivos sueltos.
    expect(Storage::disk('local')->allFiles("comunicados/{$this->borrador->id}"))->toBe([$adjunto->path]);
});

test('el duplicado lleva su propia copia de los documentos', function () {
    subirPdfDePrueba($this, $this->borrador)->assertCreated();

    $id = $this->actingAs($this->autor, 'sanctum')
        ->postJson("/api/v1/comunicados/{$this->borrador->id}/duplicar")
        ->assertCreated()
        ->json('datos.id');

    $copia = Comunicado::find($id);
    $original = $this->borrador->adjuntos()->first();

    expect($copia->adjuntos()->first()->path)->toStartWith("comunicados/{$copia->id}/adjuntos/")
        ->and($copia->adjuntos()->first()->nombre_original)->toBe('Circular-045.pdf');

    // Eliminar la copia no toca los archivos del original.
    $this->actingAs($this->autor, 'sanctum')->deleteJson("/api/v1/comunicados/{$copia->id}")->assertOk();

    Storage::disk('local')->assertExists($original->path);
    expect(Storage::disk('local')->allFiles("comunicados/{$copia->id}"))->toBe([]);
});

test('la migración que quita la portada se lleva también sus archivos', function () {
    $migracion = require database_path('migrations/2026_11_10_000200_quitar_portada_de_comunicados.php');
    $migracion->down();

    Storage::disk('local')->put("comunicados/{$this->borrador->id}/portada-vieja.jpg", 'imagen');
    DB::table('comunicados')->where('id', $this->borrador->id)
        ->update(['portada_path' => "comunicados/{$this->borrador->id}/portada-vieja.jpg"]);

    $migracion->up();

    expect(Schema::hasColumn('comunicados', 'portada_path'))->toBeFalse();
    Storage::disk('local')->assertMissing("comunicados/{$this->borrador->id}/portada-vieja.jpg");
});
