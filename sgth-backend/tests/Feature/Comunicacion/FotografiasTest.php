<?php

use App\Enums\OrigenFotografia;
use App\Enums\RegimenLaboral;
use App\Models\Comunicacion\FotografiaServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/*
| Las fotografías de los servidores (docs/diseno-comunicacion-social.md, 5.1;
| fase 1.1): subir, reemplazar, restaurar, retirar, la imagen con su permiso
| y la carga masiva por cédula.
|
| Las fotos se arman con GD en la prueba: un archivo de verdad, no un falso
| vacío, porque lo que se prueba es justamente cómo se recorta y se gira.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

/** Un JPEG pintado por `$pintar` y, si se pide, con la orientación EXIF. */
function jpegDePruebaFotografia(int $ancho, int $alto, ?Closure $pintar = null, int $orientacion = 1): string
{
    $imagen = imagecreatetruecolor($ancho, $alto);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 200, 200, 200));
    if ($pintar) {
        $pintar($imagen);
    }

    ob_start();
    imagejpeg($imagen, null, 95);
    $jpeg = (string) ob_get_clean();

    if ($orientacion === 1) {
        return $jpeg;
    }

    // Un bloque APP1 mínimo: la cabecera TIFF y una sola etiqueta, Orientation.
    $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1)
        .pack('n', 0x0112).pack('n', 3).pack('N', 1).pack('n', $orientacion)."\0\0"
        .pack('N', 0);
    $carga = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($carga) + 2).$carga.substr($jpeg, 2);
}

function archivoDePruebaFotografia(string $nombre, int $ancho = 900, int $alto = 1200): UploadedFile
{
    return UploadedFile::fake()->createWithContent($nombre, jpegDePruebaFotografia($ancho, $alto));
}

/** El color de un píxel de un JPEG, como [r, g, b]. */
function colorEnFotografia(string $jpeg, int $x, int $y): array
{
    $imagen = imagecreatefromstring($jpeg);
    $c = imagecolorat($imagen, $x, $y);

    return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
}

function servidorDePruebaFotografia(string $cedula, bool $activo = true): Servidor
{
    return Servidor::create([
        'cedula' => $cedula, 'nombre' => 'Ana', 'apellido' => "Prueba {$cedula}",
        'regimen_laboral' => RegimenLaboral::LOSEP, 'estado' => $activo,
    ]);
}

function usuarioDePruebaFotografia(string $rol, ?int $servidorId = null): User
{
    $usuario = User::factory()->create(['servidor_id' => $servidorId]);
    $usuario->assignRole($rol);

    return $usuario;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolPermisoSeeder::class);

    $this->ana = servidorDePruebaFotografia('0801111111');
    $this->luis = servidorDePruebaFotografia('0802222222');

    $this->comunicacion = usuarioDePruebaFotografia('comunicacion-social');
});

function subirFotoDePrueba(object $prueba, Servidor $servidor, ?UploadedFile $foto = null)
{
    return $prueba->actingAs($prueba->comunicacion, 'sanctum')
        ->post("/api/v1/comunicacion/servidores/{$servidor->id}/fotografia", [
            'foto' => $foto ?? archivoDePruebaFotografia('ana.jpg'),
        ], ['Accept' => 'application/json']);
}

// ── Subir y procesar ───────────────────────────────────────────────────────

test('comunicacion sube una foto y queda vigente en 600x800 con su miniatura', function () {
    $respuesta = subirFotoDePrueba($this, $this->ana)->assertCreated();

    $foto = FotografiaServidor::findOrFail($respuesta->json('datos.id'));
    $disco = Storage::disk('local');

    expect($foto->vigente)->toBeTrue()
        ->and($foto->origen)->toBe(OrigenFotografia::INDIVIDUAL)
        ->and($foto->subida_por)->toBe($this->comunicacion->id)
        ->and(getimagesizefromstring($disco->get($foto->archivo))[0])->toBe(600)
        ->and(getimagesizefromstring($disco->get($foto->archivo))[1])->toBe(800)
        ->and(getimagesizefromstring($disco->get($foto->miniatura))[0])->toBe(160)
        ->and(getimagesizefromstring($disco->get($foto->miniatura))[1])->toBe(160)
        ->and($respuesta->json('datos.subida_por.id'))->toBe($this->comunicacion->id);

    // Queda quién la subió en el registro de actividad.
    expect(Activity::where('subject_type', FotografiaServidor::class)->where('subject_id', $foto->id)->exists())->toBeTrue();
});

test('una foto apaisada se recorta centrada a 3:4', function () {
    // Tres franjas verticales: rojo, verde y azul. El recorte 900x1200 de una
    // foto de 1600x1200 se queda con el centro: el verde.
    $jpeg = jpegDePruebaFotografia(1600, 1200, function ($img) {
        imagefilledrectangle($img, 0, 0, 532, 1199, imagecolorallocate($img, 255, 0, 0));
        imagefilledrectangle($img, 533, 0, 1066, 1199, imagecolorallocate($img, 0, 255, 0));
        imagefilledrectangle($img, 1067, 0, 1599, 1199, imagecolorallocate($img, 0, 0, 255));
    });

    $id = subirFotoDePrueba($this, $this->ana, UploadedFile::fake()->createWithContent('a.jpg', $jpeg))
        ->assertCreated()->json('datos.id');
    $guardada = Storage::disk('local')->get(FotografiaServidor::findOrFail($id)->archivo);

    [$r, $g, $b] = colorEnFotografia($guardada, 300, 400);
    expect($g)->toBeGreaterThan(200)->and($r)->toBeLessThan(60)->and($b)->toBeLessThan(60);
});

test('una foto con orientacion exif se endereza antes de recortar', function () {
    // Guardada acostada (800x600): la mitad izquierda roja, la derecha azul,
    // con la marca «girar 90 grados a la derecha». Al girarla, la izquierda
    // queda arriba.
    $jpeg = jpegDePruebaFotografia(800, 600, function ($img) {
        imagefilledrectangle($img, 0, 0, 399, 599, imagecolorallocate($img, 255, 0, 0));
        imagefilledrectangle($img, 400, 0, 799, 599, imagecolorallocate($img, 0, 0, 255));
    }, orientacion: 6);

    $id = subirFotoDePrueba($this, $this->ana, UploadedFile::fake()->createWithContent('a.jpg', $jpeg))
        ->assertCreated()->json('datos.id');
    $guardada = Storage::disk('local')->get(FotografiaServidor::findOrFail($id)->archivo);

    [$rArriba, , $bArriba] = colorEnFotografia($guardada, 300, 100);
    [$rAbajo, , $bAbajo] = colorEnFotografia($guardada, 300, 700);

    expect($rArriba)->toBeGreaterThan(200)->and($bArriba)->toBeLessThan(60)
        ->and($bAbajo)->toBeGreaterThan(200)->and($rAbajo)->toBeLessThan(60);
});

test('una foto demasiado pequena se rechaza con el motivo', function () {
    subirFotoDePrueba($this, $this->ana, archivoDePruebaFotografia('chica.jpg', 280, 380))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'La foto es demasiado pequeña: recortada a 3:4 debe medir al menos 300 × 400 píxeles.');

    expect(FotografiaServidor::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('un archivo que no es imagen no pasa la validacion', function () {
    subirFotoDePrueba($this, $this->ana, UploadedFile::fake()->createWithContent('falsa.jpg', 'esto no es una foto'))
        ->assertStatus(422);

    expect(FotografiaServidor::count())->toBe(0);
});

// ── Reemplazar, restaurar y retirar ────────────────────────────────────────

test('reemplazar deja la anterior en el historial y se puede restaurar', function () {
    $primera = subirFotoDePrueba($this, $this->ana)->json('datos.id');
    $segunda = subirFotoDePrueba($this, $this->ana)->json('datos.id');

    expect(FotografiaServidor::find($primera)->vigente)->toBeFalse()
        ->and(FotografiaServidor::find($segunda)->vigente)->toBeTrue();

    $historial = $this->actingAs($this->comunicacion, 'sanctum')
        ->getJson("/api/v1/comunicacion/servidores/{$this->ana->id}/fotografias")
        ->assertOk()->json('datos');
    expect(array_column($historial, 'id'))->toBe([$segunda, $primera]);

    $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson("/api/v1/comunicacion/fotografias/{$primera}/restaurar")
        ->assertOk()
        ->assertJsonPath('datos.vigente', true);

    expect(FotografiaServidor::find($primera)->vigente)->toBeTrue()
        ->and(FotografiaServidor::find($segunda)->vigente)->toBeFalse();

    $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson("/api/v1/comunicacion/fotografias/{$primera}/restaurar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Esa foto ya es la vigente.');
});

test('retirar deja al servidor sin foto sin borrar el archivo', function () {
    $id = subirFotoDePrueba($this, $this->ana)->json('datos.id');

    $this->actingAs($this->comunicacion, 'sanctum')
        ->deleteJson("/api/v1/comunicacion/servidores/{$this->ana->id}/fotografia")
        ->assertOk();

    $foto = FotografiaServidor::findOrFail($id);
    expect($foto->vigente)->toBeFalse()
        ->and(Storage::disk('local')->exists($foto->archivo))->toBeTrue();

    $this->actingAs($this->comunicacion, 'sanctum')
        ->deleteJson("/api/v1/comunicacion/servidores/{$this->ana->id}/fotografia")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'El servidor no tiene una foto vigente.');
});

test('la base no admite dos fotos vigentes del mismo servidor', function () {
    subirFotoDePrueba($this, $this->ana)->assertCreated();

    expect(fn () => FotografiaServidor::create([
        'servidor_id' => $this->ana->id, 'archivo' => 'x.jpg', 'miniatura' => 'x-m.jpg',
        'origen' => OrigenFotografia::INDIVIDUAL, 'vigente' => true,
    ]))->toThrow(QueryException::class);
});

// ── Quién entra ────────────────────────────────────────────────────────────

test('sin gestionar-fotografias no se lista ni se sube', function (string $rol) {
    $usuario = usuarioDePruebaFotografia($rol);

    $this->actingAs($usuario, 'sanctum')->getJson('/api/v1/comunicacion/fotografias')->assertForbidden();
    $this->actingAs($usuario, 'sanctum')
        ->post("/api/v1/comunicacion/servidores/{$this->ana->id}/fotografia",
            ['foto' => archivoDePruebaFotografia('a.jpg')], ['Accept' => 'application/json'])
        ->assertForbidden();
})->with(['servidor', 'responsable-identidad', 'admin-uath', 'auditor']);

test('la imagen vigente la ven el propio servidor, talento humano y quien consulta credenciales', function () {
    $id = subirFotoDePrueba($this, $this->ana)->json('datos.id');
    $url = "/api/v1/comunicacion/fotografias/{$id}/imagen";

    $propio = usuarioDePruebaFotografia('servidor', $this->ana->id);
    $otro = usuarioDePruebaFotografia('servidor', $this->luis->id);

    $this->actingAs($propio, 'sanctum')->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs(usuarioDePruebaFotografia('admin-uath'), 'sanctum')->get($url)->assertOk();
    $this->actingAs(usuarioDePruebaFotografia('auditor'), 'sanctum')->get($url)->assertOk();
    $this->actingAs($otro, 'sanctum')->getJson($url)->assertForbidden();

    $miniatura = $this->actingAs($propio, 'sanctum')->get("{$url}?tamano=miniatura")->assertOk();
    expect(getimagesize($miniatura->baseResponse->getFile()->getPathname())[0])->toBe(160);
});

test('una foto que ya no es vigente solo la ve comunicacion', function () {
    $id = subirFotoDePrueba($this, $this->ana)->json('datos.id');
    $this->actingAs($this->comunicacion, 'sanctum')
        ->deleteJson("/api/v1/comunicacion/servidores/{$this->ana->id}/fotografia")->assertOk();

    $url = "/api/v1/comunicacion/fotografias/{$id}/imagen";

    $this->actingAs(usuarioDePruebaFotografia('servidor', $this->ana->id), 'sanctum')->getJson($url)->assertForbidden();
    $this->actingAs(usuarioDePruebaFotografia('auditor'), 'sanctum')->getJson($url)->assertForbidden();
    $this->actingAs($this->comunicacion, 'sanctum')->get($url)->assertOk();
});

// ── Listado ────────────────────────────────────────────────────────────────

test('el listado dice quien tiene foto y filtra', function () {
    $id = subirFotoDePrueba($this, $this->ana)->json('datos.id');
    $inactivo = servidorDePruebaFotografia('0803333333', activo: false);

    $listar = fn (string $query = '') => collect($this->actingAs($this->comunicacion, 'sanctum')
        ->getJson("/api/v1/comunicacion/fotografias{$query}")->assertOk()->json('datos'))->keyBy('cedula');

    $todos = $listar();
    expect($todos->keys()->sort()->values()->all())->toBe(['0801111111', '0802222222'])
        ->and($todos['0801111111']['fotografia']['id'])->toBe($id)
        ->and($todos['0802222222']['fotografia'])->toBeNull();

    expect($listar('?foto=con')->keys()->all())->toBe(['0801111111'])
        ->and($listar('?foto=sin')->keys()->all())->toBe(['0802222222'])
        ->and($listar('?estado=inactivos')->keys()->all())->toBe([$inactivo->cedula])
        ->and($listar('?buscar=0802222')->keys()->all())->toBe(['0802222222'])
        ->and($listar('?subida_antes_de='.now()->addDay()->toDateString())->keys()->all())->toBe(['0801111111'])
        ->and($listar('?subida_antes_de='.now()->subDay()->toDateString())->keys()->all())->toBe([]);
});

test('el perfil y la ficha del expediente traen la foto vigente', function () {
    $propio = usuarioDePruebaFotografia('servidor', $this->ana->id);

    $this->actingAs($propio, 'sanctum')->getJson('/api/v1/auth/perfil')
        ->assertOk()->assertJsonPath('datos.servidor.fotografia', null);

    $id = subirFotoDePrueba($this, $this->ana)->json('datos.id');

    // Otra instancia, como en una petición nueva: la anterior ya tiene cargada
    // la relación, sin foto.
    $propio = $propio->fresh();
    $this->actingAs($propio, 'sanctum')->getJson('/api/v1/auth/perfil')
        ->assertOk()->assertJsonPath('datos.servidor.fotografia.id', $id);
    $this->actingAs($propio, 'sanctum')->getJson("/api/v1/expediente/servidores/{$this->ana->id}")
        ->assertOk()->assertJsonPath('datos.fotografia.id', $id);
});

// ── Carga masiva ───────────────────────────────────────────────────────────

test('la simulacion clasifica cada archivo por su nombre', function () {
    subirFotoDePrueba($this, $this->luis)->assertCreated();
    servidorDePruebaFotografia('0803333333', activo: false);

    $simular = fn (bool $reemplazar) => $this->actingAs($this->comunicacion, 'sanctum')
        ->postJson('/api/v1/comunicacion/fotografias/carga/simular', [
            'archivos' => [
                '0801111111.jpg',              // sin foto: nueva
                '0802222222 - Luis Prueba.JPG', // ya tiene foto
                'carpeta/foto sin cedula.jpg',
                '0809999999.jpg',              // nadie con esa cédula
                '0801111111 (2).jpg',          // repetida en el lote
                '0803333333.png',              // inactivo
            ],
            'reemplazar' => $reemplazar,
        ])->assertOk()->json('datos');

    $sinReemplazar = $simular(false);
    expect(array_column($sinReemplazar['archivos'], 'resultado'))->toBe([
        'nueva', 'ya_tiene_foto', 'sin_cedula', 'sin_servidor', 'repetida', 'inactivo',
    ])
        ->and($sinReemplazar['resumen'])->toBe(['total' => 6, 'se_cargan' => 1, 'se_omiten' => 5])
        ->and($sinReemplazar['archivos'][0]['servidor']['id'])->toBe($this->ana->id);

    $reemplazando = $simular(true);
    expect($reemplazando['archivos'][1]['resultado'])->toBe('reemplaza')
        ->and($reemplazando['resumen']['se_cargan'])->toBe(2);

    // La simulación no toca nada.
    expect(FotografiaServidor::count())->toBe(1);
});

test('la carga sube la tanda y cuenta lo que no pudo usar', function () {
    $sinFoto = servidorDePruebaFotografia('0804444444');
    $previa = subirFotoDePrueba($this, $this->luis)->json('datos.id');

    $informe = $this->actingAs($this->comunicacion, 'sanctum')
        ->post('/api/v1/comunicacion/fotografias/carga', [
            'fotos' => [
                archivoDePruebaFotografia('0801111111.jpg'),
                UploadedFile::fake()->createWithContent('0804444444.jpg', 'esto no es una foto'),
                archivoDePruebaFotografia('0802222222.jpg'),
            ],
        ], ['Accept' => 'application/json'])
        ->assertOk()->json('datos');

    expect(array_column($informe['archivos'], 'resultado'))->toBe(['nueva', 'ilegible', 'ya_tiene_foto'])
        ->and($informe['archivos'][1]['detalle'])->toBe('El archivo no es una imagen JPG, PNG o WebP que se pueda leer.')
        ->and($informe['resumen'])->toBe(['total' => 3, 'se_cargan' => 1, 'se_omiten' => 2]);

    $nueva = $this->ana->fotografiaVigente()->firstOrFail();
    expect($nueva->origen)->toBe(OrigenFotografia::CARGA_MASIVA)
        ->and($sinFoto->fotografiaVigente()->exists())->toBeFalse()
        ->and($this->luis->fotografiaVigente()->value('id'))->toBe($previa);
});

test('una tanda tiene un maximo de fotos', function () {
    $fotos = array_map(fn (int $i) => archivoDePruebaFotografia("08011111{$i}.jpg", 300, 400), range(10, 20));

    $this->actingAs($this->comunicacion, 'sanctum')
        ->post('/api/v1/comunicacion/fotografias/carga', ['fotos' => $fotos], ['Accept' => 'application/json'])
        ->assertStatus(422);
});
