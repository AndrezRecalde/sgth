<?php

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\OrigenRecursoIdentidad;
use App\Enums\VarianteIdentidad;
use App\Models\Comunicacion\DatosInstitucionales;
use App\Models\Comunicacion\RecursoIdentidad;
use App\Models\User;
use Database\Seeders\RolPermisoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

/*
| La identidad institucional (docs/diseno-comunicacion-social.md, 4; fase 2.1):
| las variantes del logo con su vigencia y respaldo, la carga inicial, los
| datos de la institución y lo que se sirve sin sesión.
|
| La migración siembra los archivos de hoy en el disco: aquí se vuelve a correr
| sobre un disco falso, para que cada prueba empiece con esa carga inicial.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

function imagenDePruebaIdentidad(int $ancho, int $alto, string $tipo = 'png'): string
{
    $imagen = imagecreatetruecolor($ancho, $alto);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 30, 140, 60));

    ob_start();
    $tipo === 'png' ? imagepng($imagen) : imagejpeg($imagen, null, 90);

    return (string) ob_get_clean();
}

function archivoDePruebaIdentidad(int $ancho, int $alto, string $tipo = 'png'): UploadedFile
{
    return UploadedFile::fake()->createWithContent("logo.{$tipo}", imagenDePruebaIdentidad($ancho, $alto, $tipo));
}

function usuarioDePruebaIdentidad(string $rol): User
{
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);

    return $usuario;
}

function subirVersionDePrueba(object $prueba, string $variante, UploadedFile $archivo, ?string $desde = null)
{
    return $prueba->actingAs($prueba->responsable, 'sanctum')->post(
        "/api/v1/comunicacion/identidad/{$variante}",
        array_filter(['archivo' => $archivo, 'vigente_desde' => $desde]),
        ['Accept' => 'application/json'],
    );
}

beforeEach(function () {
    Storage::fake('local');

    // La carga inicial, sobre el disco falso.
    $migracion = require database_path('migrations/2026_11_03_000000_crear_identidad_institucional.php');
    $migracion->down();
    $migracion->up();

    $this->seed(RolPermisoSeeder::class);
    $this->identidad = app(IdentidadInstitucionalServiceInterface::class);
    $this->responsable = usuarioDePruebaIdentidad('responsable-identidad');
    $this->inicial = $this->identidad->version(VarianteIdentidad::IMAGOTIPO_HORIZONTAL);
});

// ── Carga inicial ──────────────────────────────────────────────────────────

test('la migracion siembra los logos de hoy y los datos de la institucion', function () {
    $sembradas = RecursoIdentidad::orderBy('id')->get();

    expect($sembradas->pluck('variante')->map->value->all())
        ->toBe(['isotipo', 'imagotipo_horizontal', 'hoja_membretada'])
        ->and($sembradas->every(fn ($r) => $r->origen === OrigenRecursoIdentidad::CARGA_INICIAL))->toBeTrue()
        ->and($sembradas->every(fn ($r) => Storage::disk('local')->exists($r->archivo)))->toBeTrue()
        ->and($this->inicial->ancho)->toBe(2199)
        ->and(DatosInstitucionales::sole()->ruc)->toBe('0860000160001');
});

// ── Vigencia y respaldo ────────────────────────────────────────────────────

test('cada fecha toma la version vigente y una programada no rige antes de tiempo', function () {
    $desde = Carbon::today()->addDays(10);
    $nueva = subirVersionDePrueba($this, 'imagotipo_horizontal', archivoDePruebaIdentidad(1400, 400), $desde->toDateString())
        ->assertCreated()
        ->assertJsonPath('datos.estado', 'programada')
        ->json('datos.id');

    $v = VarianteIdentidad::IMAGOTIPO_HORIZONTAL;
    expect($this->identidad->version($v)->id)->toBe($this->inicial->id)
        ->and($this->identidad->version($v, $desde->copy()->addDays(5))->id)->toBe($nueva)
        // Un documento anterior a la primera versión usa la más antigua.
        ->and($this->identidad->version($v, Carbon::today()->subYears(3))->id)->toBe($this->inicial->id);

    $this->actingAs($this->responsable, 'sanctum')
        ->deleteJson("/api/v1/comunicacion/identidad/versiones/{$nueva}")
        ->assertOk()
        ->assertJsonPath('datos.estado', 'anulada');

    expect($this->identidad->version($v, $desde->copy()->addDays(5))->id)->toBe($this->inicial->id);
});

test('sin version propia se usa el respaldo, y el secundario no tiene', function () {
    expect($this->identidad->resolver(VarianteIdentidad::LOGO_DOCUMENTOS)->id)->toBe($this->inicial->id)
        ->and($this->identidad->resolver(VarianteIdentidad::IMAGOTIPO_NEGATIVO)->id)->toBe($this->inicial->id)
        ->and($this->identidad->resolver(VarianteIdentidad::LOGO_SECUNDARIO_DOCUMENTOS))->toBeNull();

    $propia = subirVersionDePrueba($this, 'logo_documentos', archivoDePruebaIdentidad(1300, 500))->assertCreated()->json('datos.id');

    expect($this->identidad->resolver(VarianteIdentidad::LOGO_DOCUMENTOS)->id)->toBe($propia)
        ->and($this->identidad->archivo(VarianteIdentidad::LOGO_DOCUMENTOS))->toBe(
            Storage::disk('local')->path(RecursoIdentidad::find($propia)->archivo)
        );
});

// ── Validaciones ───────────────────────────────────────────────────────────

test('cada variante exige su formato, su tamano y su proporcion', function (string $variante, UploadedFile $archivo, string $mensaje) {
    subirVersionDePrueba($this, $variante, $archivo)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, $mensaje));
})->with([
    'isotipo en jpg'          => ['isotipo', fn () => archivoDePruebaIdentidad(600, 600, 'jpg'), 'el archivo debe ser PNG'],
    'isotipo no cuadrado'     => ['isotipo', fn () => archivoDePruebaIdentidad(800, 600), 'no tiene la proporción'],
    'isotipo pequeño'         => ['isotipo', fn () => archivoDePruebaIdentidad(300, 300), 'mide 300 × 300'],
    'horizontal angosto'      => ['imagotipo_horizontal', fn () => archivoDePruebaIdentidad(800, 300), 'mide 800 × 300'],
    // Pasa el mínimo (1240 × 1754) y falla solo por la proporción.
    'hoja que no es A4'       => ['hoja_membretada', fn () => archivoDePruebaIdentidad(2400, 2400, 'jpg'), 'no tiene la proporción'],
]);

test('la hoja membretada se acepta en jpg con la proporcion del a4', function () {
    subirVersionDePrueba($this, 'hoja_membretada', archivoDePruebaIdentidad(1240, 1754, 'jpg'))
        ->assertCreated()
        ->assertJsonPath('datos.mime', 'image/jpeg');
});

test('un svg no se acepta', function () {
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    subirVersionDePrueba($this, 'isotipo', $svg)->assertStatus(422);
});

test('una version no puede empezar antes que la ultima', function () {
    subirVersionDePrueba($this, 'imagotipo_horizontal', archivoDePruebaIdentidad(1400, 400), Carbon::yesterday()->toDateString())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'no puede empezar antes'));
});

// ── Volver a una versión y anular ──────────────────────────────────────────

test('volver a una version crea otra con el mismo archivo', function () {
    subirVersionDePrueba($this, 'imagotipo_horizontal', archivoDePruebaIdentidad(1400, 400))->assertCreated();

    $vuelta = $this->actingAs($this->responsable, 'sanctum')
        ->postJson("/api/v1/comunicacion/identidad/versiones/{$this->inicial->id}/reutilizar")
        ->assertCreated()
        ->assertJsonPath('datos.estado', 'vigente')
        ->assertJsonPath('datos.origen', 'reutilizada')
        ->json('datos.id');

    expect(RecursoIdentidad::find($vuelta)->archivo)->toBe($this->inicial->archivo)
        ->and($this->identidad->version(VarianteIdentidad::IMAGOTIPO_HORIZONTAL)->id)->toBe($vuelta);

    $this->actingAs($this->responsable, 'sanctum')
        ->postJson("/api/v1/comunicacion/identidad/versiones/{$vuelta}/reutilizar")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Esa versión ya es la vigente.');
});

test('una version que ya rige no se anula', function () {
    $this->actingAs($this->responsable, 'sanctum')
        ->deleteJson("/api/v1/comunicacion/identidad/versiones/{$this->inicial->id}")
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'Solo se puede anular una versión programada'));
});

// ── Quién entra ────────────────────────────────────────────────────────────

test('solo la persona designada administra la identidad', function (string $rol, int $estado) {
    $this->actingAs(usuarioDePruebaIdentidad($rol), 'sanctum')
        ->getJson('/api/v1/comunicacion/identidad')
        ->assertStatus($estado);
})->with([
    'responsable de identidad' => ['responsable-identidad', 200],
    'administrador TI'         => ['admin-ti', 200],
    'equipo de comunicación'   => ['comunicacion-social', 403],
    'servidor'                 => ['servidor', 403],
]);

test('el estado lista las seis variantes con la que se usa hoy', function () {
    $variantes = collect($this->actingAs($this->responsable, 'sanctum')
        ->getJson('/api/v1/comunicacion/identidad')
        ->assertOk()
        ->json('datos.variantes'))->keyBy('variante');

    expect($variantes->keys()->all())->toBe(array_column(VarianteIdentidad::cases(), 'value'))
        ->and($variantes['imagotipo_horizontal']['vigente']['estado'])->toBe('vigente')
        ->and($variantes['logo_documentos']['vigente'])->toBeNull()
        ->and($variantes['logo_documentos']['en_uso'])->toBe('imagotipo_horizontal')
        ->and($variantes['logo_secundario_documentos']['en_uso'])->toBeNull()
        ->and($variantes['isotipo']['obligatoria'])->toBeTrue();
});

// ── Sin sesión ─────────────────────────────────────────────────────────────

test('sin sesion se ven los logos vigentes y los datos, no los programados', function () {
    $programada = subirVersionDePrueba($this, 'imagotipo_horizontal', archivoDePruebaIdentidad(1400, 400), Carbon::tomorrow()->toDateString())
        ->json('datos.id');

    $manifiesto = $this->getJson('/api/v1/identidad')->assertOk()->json('datos');
    expect($manifiesto['variantes']['imagotipo_horizontal']['version'])->toBe($this->inicial->id)
        ->and($manifiesto['variantes']['logo_documentos']['respaldo'])->toBeTrue()
        ->and($manifiesto['variantes']['logo_secundario_documentos'])->toBeNull()
        ->and($manifiesto['datos']['siglas'])->toBe('GADPE')
        ->and($manifiesto['iconos'])->toHaveKeys(['32', '180', '192', '512']);

    $this->get("/api/v1/identidad/imagotipo_horizontal?v={$this->inicial->id}")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    // La programada solo la ve quien administra, por su id.
    $this->actingAs($this->responsable, 'sanctum')
        ->get("/api/v1/comunicacion/identidad/versiones/{$programada}/archivo")
        ->assertOk();

    $this->getJson('/api/v1/identidad/escudo')->assertNotFound();
});

test('el icono sale cuadrado del isotipo y solo en los tamanos de la app', function () {
    $icono = $this->get('/api/v1/identidad/icono/32')->assertOk()->assertHeader('Content-Type', 'image/png');
    [$ancho, $alto] = getimagesize($icono->baseResponse->getFile()->getPathname());
    expect([$ancho, $alto])->toBe([32, 32]);

    $this->getJson('/api/v1/identidad/icono/64')->assertNotFound();
});

// ── Datos de la institución ────────────────────────────────────────────────

test('los datos de la institucion se actualizan y queda quien lo hizo', function () {
    $datos = [
        'nombre_oficial' => 'GOBIERNO AUTÓNOMO DESCENTRALIZADO PROVINCIAL DE ESMERALDAS',
        'siglas' => 'GADPE', 'nombre_corto' => 'Prefectura de Esmeraldas', 'ruc' => '0860000160001',
        'direccion' => 'Av. Nueva 123, Esmeraldas', 'telefono' => '06-2700000', 'sitio_web' => 'www.gadpe.gob.ec',
    ];

    $this->actingAs($this->responsable, 'sanctum')
        ->putJson('/api/v1/comunicacion/identidad/datos', $datos)
        ->assertOk()
        ->assertJsonPath('datos.direccion', 'Av. Nueva 123, Esmeraldas')
        ->assertJsonPath('datos.actualizado_por.id', $this->responsable->id);

    expect(Activity::where('subject_type', DatosInstitucionales::class)->where('causer_id', $this->responsable->id)->exists())->toBeTrue();

    $this->actingAs($this->responsable, 'sanctum')
        ->putJson('/api/v1/comunicacion/identidad/datos', [...$datos, 'ruc' => '12345'])
        ->assertStatus(422)
        ->assertJsonPath('errores.ruc.0', 'El RUC tiene 13 dígitos.');
});
