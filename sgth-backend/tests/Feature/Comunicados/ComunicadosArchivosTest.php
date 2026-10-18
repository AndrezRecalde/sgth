<?php

use App\Models\Comunicados\Comunicado;
use App\Models\Comunicados\ComunicadoAdjunto;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| La portada y los PDF de una publicación (docs/diseno-noticias-comunicados.md,
| regla 6): van al disco privado, se sirven por controlador y solo se cambian
| en un borrador. Un duplicado se lleva su copia de cada archivo.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function portadaDePrueba(int $ancho = 1280, int $alto = 720, string $tipo = 'jpg'): UploadedFile
{
    $imagen = imagecreatetruecolor($ancho, $alto);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 124, 58, 237));

    ob_start();
    $tipo === 'png' ? imagepng($imagen) : imagejpeg($imagen, null, 85);

    return UploadedFile::fake()->createWithContent("portada.{$tipo}", (string) ob_get_clean());
}

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

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->seed(RolPermisoSeeder::class);
    $this->autor = usuarioDeComunicados('asistente-uath');
    $this->admin = usuarioDeComunicados('admin-uath');
    servidorDeComunicados(unidadDeComunicados('Dirección de Talento Humano'));
    $this->borrador = borradorDeComunicado($this->autor);
});

test('la portada va al disco privado y se ve con sesión de Talento Humano', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/portada", ['portada' => portadaDePrueba()], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('datos.tiene_portada', true);

    $ruta = $this->borrador->fresh()->portada_path;
    expect($ruta)->toStartWith("comunicados/{$this->borrador->id}/portada-")->toEndWith('.jpg');
    Storage::disk('local')->assertExists($ruta);
    expect(Storage::disk('public')->allFiles())->toBe([]);

    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/comunicados/{$this->borrador->id}/portada")
        ->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($respuesta->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('reemplazar la portada borra la anterior; quitarla deja la publicación sin ella', function () {
    $subir = fn (UploadedFile $archivo) => $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/portada", ['portada' => $archivo], ['Accept' => 'application/json'])
        ->assertOk();

    $subir(portadaDePrueba());
    $primera = $this->borrador->fresh()->portada_path;

    $subir(portadaDePrueba(1600, 900, 'png'));
    $segunda = $this->borrador->fresh()->portada_path;

    expect($segunda)->toEndWith('.png');
    Storage::disk('local')->assertMissing($primera);
    Storage::disk('local')->assertExists($segunda);

    $this->actingAs($this->autor, 'sanctum')
        ->deleteJson("/api/v1/comunicados/{$this->borrador->id}/portada")
        ->assertOk();

    expect($this->borrador->fresh()->portada_path)->toBeNull();
    Storage::disk('local')->assertMissing($segunda);

    $this->actingAs($this->autor, 'sanctum')
        ->getJson("/api/v1/comunicados/{$this->borrador->id}/portada")
        ->assertNotFound();
});

test('una portada chica o que no es imagen se rechaza', function (UploadedFile $archivo) {
    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/portada", ['portada' => $archivo], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['portada'], 'errores');
})->with([
    'menor de 640 × 360' => fn () => portadaDePrueba(600, 300),
    'un PDF'             => fn () => archivoRealDePrueba('portada.jpg', "%PDF-1.4\n%%EOF\n"),
]);

test('los PDF se agregan en orden y se descargan con su nombre', function () {
    foreach (['Circular-045.pdf', '../../Formato de turnos.pdf'] as $nombre) {
        $this->actingAs($this->autor, 'sanctum')
            ->post("/api/v1/comunicados/{$this->borrador->id}/adjuntos", ['archivo' => pdfDePrueba($nombre)], ['Accept' => 'application/json'])
            ->assertCreated();
    }

    $adjuntos = $this->borrador->adjuntos()->get();
    expect($adjuntos->pluck('nombre_original')->all())->toBe(['Circular-045.pdf', 'Formato de turnos.pdf'])
        ->and($adjuntos->pluck('orden')->all())->toBe([1, 2]);

    $adjunto = $adjuntos->first();
    expect($adjunto->path)->toStartWith("comunicados/{$this->borrador->id}/adjuntos/");

    $respuesta = $this->actingAs($this->admin, 'sanctum')
        ->get("/api/v1/comunicados/{$this->borrador->id}/adjuntos/{$adjunto->id}")
        ->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain('Circular-045.pdf');

    $this->actingAs($this->autor, 'sanctum')
        ->getJson("/api/v1/comunicados/{$this->borrador->id}")
        ->assertJsonCount(2, 'datos.adjuntos')
        ->assertJsonPath('datos.adjuntos.0.nombre', 'Circular-045.pdf')
        ->assertJsonMissingPath('datos.adjuntos.0.path');
});

test('un archivo que no es PDF no entra aunque se llame .pdf', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->post(
            "/api/v1/comunicados/{$this->borrador->id}/adjuntos",
            ['archivo' => archivoRealDePrueba('falso.pdf', '<html><body><script>alert(1)</script></body></html>')],
            ['Accept' => 'application/json'],
        )
        ->assertStatus(422)
        ->assertJsonPath('errores.archivo.0', 'El documento debe ser un PDF.');

    // Y uno que sí lo es, leído igual por su contenido, entra.
    $this->actingAs($this->autor, 'sanctum')
        ->post(
            "/api/v1/comunicados/{$this->borrador->id}/adjuntos",
            ['archivo' => archivoRealDePrueba('circular.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n")],
            ['Accept' => 'application/json'],
        )
        ->assertCreated();
});

test('hasta cinco documentos por publicación', function () {
    foreach (range(1, 5) as $i) {
        $this->actingAs($this->autor, 'sanctum')
            ->post("/api/v1/comunicados/{$this->borrador->id}/adjuntos", ['archivo' => pdfDePrueba("doc-{$i}.pdf")], ['Accept' => 'application/json'])
            ->assertCreated();
    }

    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/adjuntos", ['archivo' => pdfDePrueba('sexto.pdf')], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Una publicación lleva hasta 5 documentos adjuntos: quite uno antes de agregar otro.');

    // El sexto no dejó su archivo en el disco.
    expect(Storage::disk('local')->allFiles("comunicados/{$this->borrador->id}/adjuntos"))->toHaveCount(5);
});

test('quitar un documento lo borra del disco; el de otra publicación no se alcanza', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/adjuntos", ['archivo' => pdfDePrueba()], ['Accept' => 'application/json'])
        ->assertCreated();
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

test('en lo publicado los archivos ya no cambian', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/adjuntos", ['archivo' => pdfDePrueba()], ['Accept' => 'application/json'])
        ->assertCreated();
    $adjunto = $this->borrador->adjuntos()->first();

    $this->actingAs($this->admin, 'sanctum')
        ->postJson("/api/v1/comunicados/{$this->borrador->id}/publicar")
        ->assertOk();

    $mensaje = 'Los archivos solo se cambian en un borrador. Para corregir lo publicado, retírelo y duplíquelo.';

    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/portada", ['portada' => portadaDePrueba()], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', $mensaje);
    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/adjuntos", ['archivo' => pdfDePrueba()], ['Accept' => 'application/json'])
        ->assertStatus(422);
    $this->actingAs($this->autor, 'sanctum')
        ->deleteJson("/api/v1/comunicados/{$this->borrador->id}/adjuntos/{$adjunto->id}")
        ->assertStatus(422);

    Storage::disk('local')->assertExists($adjunto->path);
    // Lo rechazado no dejó archivos sueltos.
    expect(Storage::disk('local')->allFiles("comunicados/{$this->borrador->id}"))->toBe([$adjunto->path]);
});

test('el duplicado lleva su propia copia de la portada y los documentos', function () {
    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/portada", ['portada' => portadaDePrueba()], ['Accept' => 'application/json'])
        ->assertOk();
    $this->actingAs($this->autor, 'sanctum')
        ->post("/api/v1/comunicados/{$this->borrador->id}/adjuntos", ['archivo' => pdfDePrueba()], ['Accept' => 'application/json'])
        ->assertCreated();

    $id = $this->actingAs($this->autor, 'sanctum')
        ->postJson("/api/v1/comunicados/{$this->borrador->id}/duplicar")
        ->assertCreated()
        ->assertJsonPath('datos.tiene_portada', true)
        ->json('datos.id');

    $copia = Comunicado::find($id);
    $original = $this->borrador->fresh();

    expect($copia->portada_path)->toStartWith("comunicados/{$copia->id}/")
        ->and($copia->portada_path)->not->toBe($original->portada_path)
        ->and($copia->adjuntos()->first()->path)->toStartWith("comunicados/{$copia->id}/adjuntos/")
        ->and($copia->adjuntos()->first()->nombre_original)->toBe('Circular-045.pdf');

    // Eliminar la copia no toca los archivos del original.
    $this->actingAs($this->autor, 'sanctum')->deleteJson("/api/v1/comunicados/{$copia->id}")->assertOk();

    Storage::disk('local')->assertExists($original->portada_path);
    Storage::disk('local')->assertExists($original->adjuntos()->first()->path);
    expect(Storage::disk('local')->allFiles("comunicados/{$copia->id}"))->toBe([]);
});
