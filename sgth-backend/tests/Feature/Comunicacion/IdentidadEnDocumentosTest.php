<?php

use App\Contracts\Comunicacion\IdentidadInstitucionalServiceInterface;
use App\Enums\VarianteIdentidad;
use App\Models\Comunicacion\DatosInstitucionales;
use App\Models\Comunicacion\RecursoIdentidad;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Expediente\CertificadoLaboralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

/*
| Los documentos con la identidad de su fecha (docs/diseno-comunicacion-social.md,
| 4.3 y D3; fase 2.2).
|
| Los PDF no se guardan: se arman en cada descarga. Sin la vigencia, cambiar el
| logo cambiaría también los papeles ya emitidos al reimprimirlos.
*/

uses(Tests\TestCase::class, RefreshDatabase::class);

/** Un PNG en un archivo temporal, como el que llega al subir. */
function pngTemporalDeDocumento(int $ancho, int $alto): string
{
    $imagen = imagecreatetruecolor($ancho, $alto);
    imagefill($imagen, 0, 0, imagecolorallocate($imagen, 200, 40, 40));
    $ruta = tempnam(sys_get_temp_dir(), 'logo').'.png';
    imagepng($imagen, $ruta);

    return $ruta;
}

beforeEach(function () {
    Storage::fake('local');

    // La carga inicial, sobre el disco falso.
    $migracion = require database_path('migrations/2026_11_03_000000_crear_identidad_institucional.php');
    $migracion->down();
    $migracion->up();

    $this->identidad = app(IdentidadInstitucionalServiceInterface::class);
    $this->usuario = User::factory()->create();
    $this->ruta = fn (VarianteIdentidad $v) => Storage::disk('local')->path(
        RecursoIdentidad::where('variante', $v->value)->orderBy('id')->firstOrFail()->archivo
    );
});

test('un documento toma el logo vigente a su fecha', function () {
    $inicial = ($this->ruta)(VarianteIdentidad::IMAGOTIPO_HORIZONTAL);
    $nueva = $this->identidad->subir(
        VarianteIdentidad::IMAGOTIPO_HORIZONTAL, pngTemporalDeDocumento(1400, 400),
        Carbon::today()->addDays(30)->toDateString(), 'Administración siguiente', $this->usuario,
    );

    $hoy = $this->identidad->paraDocumento();
    $despues = $this->identidad->paraDocumento(Carbon::today()->addDays(40));

    // Sin logo de documentos propio, el horizontal; sin secundario, nada.
    expect($hoy['logo'])->toBe($inicial)
        ->and($despues['logo'])->toBe($this->identidad->rutaDe($nueva))
        ->and($hoy['logoSecundario'])->toBe('')
        ->and($hoy['hojaMembretada'])->toBe(($this->ruta)(VarianteIdentidad::HOJA_MEMBRETADA))
        ->and($hoy['identidad']['siglas'])->toBe('GADPE');
});

test('un documento anterior al primer logo de documentos usa el horizontal de su fecha', function () {
    $propio = $this->identidad->subir(
        VarianteIdentidad::LOGO_DOCUMENTOS, pngTemporalDeDocumento(1300, 500), null, null, $this->usuario,
    );

    expect($this->identidad->paraDocumento()['logo'])->toBe($this->identidad->rutaDe($propio))
        ->and($this->identidad->paraDocumento(Carbon::today()->subYear())['logo'])
        ->toBe(($this->ruta)(VarianteIdentidad::IMAGOTIPO_HORIZONTAL));
});

test('el certificado laboral se reimprime con el logo de cuando se emitio', function () {
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $unidad = unidadDePrueba(['codigo' => 'UATH-LOGO', 'nombre' => 'Gestión Administrativa']);
    $puesto = puestoDePrueba($unidad);
    $servidor = Servidor::create([
        'cedula' => '1740000999', 'nombre' => 'Ana', 'apellido' => 'Pérez', 'regimen_laboral' => 'losep',
        'estado' => true, 'puesto_id' => $puesto->id, 'unidad_administrativa_id' => $unidad->id,
        'fecha_ingreso_institucion' => '2020-01-01',
    ]);
    ContratoServidor::create([
        'servidor_id' => $servidor->id, 'tipo_nombramiento' => 'nombramiento_permanente',
        'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $puesto->id,
        'fecha_inicio' => '2020-01-01', 'estado' => 'vigente', 'remuneracion' => 1234.56,
    ]);

    $certificados = app(CertificadoLaboralService::class);
    $emision = $certificados->emitir($servidor->fresh(), false, $this->usuario->id);
    $emision->update(['emitido_en' => now()->subMonth()]);

    // Después de emitido, Comunicación carga otro logo para los documentos.
    $nuevo = $this->identidad->subir(
        VarianteIdentidad::LOGO_DOCUMENTOS, pngTemporalDeDocumento(1300, 500), null, null, $this->usuario,
    );

    $logos = [];
    View::composer('pdf.expediente.certificado-laboral', function ($vista) use (&$logos) {
        $logos[] = $vista->getData()['logo'];
    });

    $certificados->pdf($emision->fresh());
    $certificados->pdf($certificados->emitir($servidor->fresh(), false, $this->usuario->id));

    expect($logos)->toBe([
        ($this->ruta)(VarianteIdentidad::IMAGOTIPO_HORIZONTAL),
        $this->identidad->rutaDe($nuevo),
    ]);
});

test('la cabecera comun lleva el nombre oficial, la dependencia y el secundario solo si hay', function () {
    DatosInstitucionales::query()->update(['nombre_oficial' => 'PREFECTURA DE PRUEBA']);
    $datos = $this->identidad->paraDocumento();

    $sinSecundario = view('pdf._membrete-institucional', [...$datos, 'dependencia' => 'Dirección de Pruebas'])->render();
    expect($sinSecundario)->toContain('PREFECTURA DE PRUEBA')
        ->and($sinSecundario)->toContain('Dirección de Pruebas')
        ->and(substr_count($sinSecundario, '<img'))->toBe(1);

    $secundario = $this->identidad->subir(
        VarianteIdentidad::LOGO_SECUNDARIO_DOCUMENTOS, pngTemporalDeDocumento(400, 400), null, null, $this->usuario,
    );
    $conSecundario = view('pdf._membrete-institucional', [
        ...$this->identidad->paraDocumento(), 'dependencia' => 'Dirección de Pruebas',
    ])->render();

    expect(substr_count($conSecundario, '<img'))->toBe(2)
        ->and($conSecundario)->toContain($this->identidad->rutaDe($secundario));
});

test('el pie lleva la direccion y el telefono de la institucion', function () {
    DatosInstitucionales::query()->update(['direccion' => 'Av. de Prueba 123', 'telefono' => '06-0000000']);

    $pie = view('pdf._pie-institucional', $this->identidad->paraDocumento())->render();

    expect($pie)->toContain('Av. de Prueba 123.')->and($pie)->toContain('06-0000000');
});
