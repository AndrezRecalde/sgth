<?php

use App\Enums\CausalVistoBueno;
use App\Enums\EstadoVistoBueno;
use App\Enums\TipoNombramiento;
use App\Models\Disciplinario\VistoBueno;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Disciplinario\VistoBuenoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * La resolución del Inspector en PDF y la referencia de la impugnación
 * (2026-10-04). Antes `documento_respaldo` era texto libre que ninguna
 * pantalla llenaba, y la impugnación no tenía dónde guardar su juicio.
 */

beforeEach(function () {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'admin-uath', 'guard_name' => 'sanctum']);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin-uath');
    $this->actingAs($this->admin, 'sanctum');

    $unidad = UnidadAdministrativa::create(['codigo' => 'VB-RES', 'nombre' => 'Unidad VB', 'nivel' => 1]);
    $puesto = Puesto::create(['codigo' => 'P-VB-RES', 'unidad_administrativa_id' => $unidad->id, 'plazas' => 5]);

    $obrero = Servidor::create([
        'cedula' => '6300000001', 'nombre' => 'Obrero', 'apellido' => 'Resolucion',
        'regimen_laboral' => 'codigo_trabajo', 'puesto_id' => $puesto->id,
        'unidad_administrativa_id' => $unidad->id, 'fecha_ingreso_institucion' => '2018-01-01',
    ]);
    ContratoServidor::create([
        'servidor_id' => $obrero->id, 'tipo_nombramiento' => TipoNombramiento::CODIGO_TRABAJO->value,
        'unidad_administrativa_id' => $unidad->id, 'puesto_id' => $puesto->id,
        'fecha_inicio' => '2018-01-01', 'estado' => 'vigente',
    ]);

    $servicio = app(VistoBuenoService::class);
    $this->tramite = $servicio->solicitar($obrero->id, [
        'causal' => CausalVistoBueno::INDISCIPLINA_DESOBEDIENCIA->value,
        'hechos' => 'Desobediencia reiterada.',
        'fecha_solicitud' => '2026-09-01',
    ], $this->admin->id);

    $this->resolver = function () use ($servicio): VistoBueno {
        $t = $servicio->transicionar($this->tramite, EstadoVistoBueno::NOTIFICADO, [], $this->admin->id);
        $t = $servicio->transicionar($t, EstadoVistoBueno::EN_INVESTIGACION, [], $this->admin->id);

        return $servicio->transicionar($t, EstadoVistoBueno::CONCEDIDO, [
            'resolucion_detalle' => 'Concedido por el Inspector.',
            'fecha_resolucion'   => '2026-09-20',
        ], $this->admin->id);
    };
    $this->url = fn () => "/api/v1/disciplinario/vistos-buenos/{$this->tramite->id}";
});

test('la resolución se adjunta en PDF, se descarga y la ruta no sale en el JSON', function () {
    ($this->resolver)();

    $this->post(($this->url)().'/documento', [
        'archivo' => UploadedFile::fake()->create('resolucion-mdt.pdf', 200, 'application/pdf'),
    ])
        ->assertOk()
        ->assertJsonPath('datos.tiene_documento', true)
        ->assertJsonPath('datos.documento_nombre', 'resolucion-mdt.pdf')
        ->assertJsonMissingPath('datos.documento_respaldo');

    $this->get(($this->url)().'/documento')
        ->assertOk()
        ->assertDownload('resolucion-mdt.pdf');
});

test('reemplazar la resolución borra el archivo anterior', function () {
    ($this->resolver)();

    $this->post(($this->url)().'/documento', ['archivo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
    $primera = VistoBueno::find($this->tramite->id)->documento_respaldo;

    $this->post(($this->url)().'/documento', ['archivo' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])
        ->assertOk();

    Storage::disk('local')->assertMissing($primera);
    Storage::disk('local')->assertExists(VistoBueno::find($this->tramite->id)->documento_respaldo);
});

test('solo PDF, y solo cuando el Inspector ya resolvió', function () {
    $this->post(($this->url)().'/documento', ['archivo' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonPath('mensaje', 'La resolución se adjunta cuando el Inspector del Trabajo se pronuncia: el trámite está en «Solicitado».');

    ($this->resolver)();

    $this->post(($this->url)().'/documento', ['archivo' => UploadedFile::fake()->image('foto.jpg')])
        ->assertUnprocessable()
        ->assertJsonPath('errores.archivo.0', 'La resolución tiene que ser un PDF.');

    $this->get(($this->url)().'/documento')->assertNotFound();
});

test('una ruta escrita a mano ya no se guarda', function () {
    // Antes `documento_respaldo` se aceptaba como texto en la transición: una
    // ruta inventada habría servido para bajar cualquier archivo del disco.
    $servicio = app(VistoBuenoService::class);
    $this->putJson(($this->url)().'/transicionar', [
        'estado' => 'notificado', 'documento_respaldo' => '../../.env',
    ])->assertOk();

    expect(VistoBueno::find($this->tramite->id)->documento_respaldo)->toBeNull();
});

test('impugnar pide el juicio y su fecha, y los anota en la cesación', function () {
    $resuelto = ($this->resolver)();
    $cesacion = $resuelto->movimientoPersonal;

    $this->putJson(($this->url)().'/transicionar', ['estado' => 'impugnado'])
        ->assertUnprocessable()
        ->assertJsonPath('errores.impugnacion_referencia.0', 'Indique el número de juicio o de causa de la impugnación.');

    $this->putJson(($this->url)().'/transicionar', [
        'estado' => 'impugnado', 'impugnacion_referencia' => 'Juicio 08281-2026-00123', 'fecha_impugnacion' => '2026-09-10',
    ])
        ->assertUnprocessable()
        ->assertJsonStructure(['errores' => ['fecha_impugnacion']]);

    $this->putJson(($this->url)().'/transicionar', [
        'estado' => 'impugnado', 'impugnacion_referencia' => 'Juicio 08281-2026-00123', 'fecha_impugnacion' => '2026-09-25',
    ])
        ->assertOk()
        ->assertJsonPath('datos.impugnacion_referencia', 'Juicio 08281-2026-00123');

    // Desde la fase 1.4 en una anotación, no en la explicación de la acción.
    expect($cesacion->fresh()->anotaciones->pluck('texto')->all())
        ->toBe(['Impugnado por el trabajador (Juicio 08281-2026-00123, 25/09/2026): '
            .'revísese con Asesoría Jurídica antes de continuar con esta cesación.'])
        ->and($cesacion->fresh()->descripcion)->not->toContain('Impugnado');
});
