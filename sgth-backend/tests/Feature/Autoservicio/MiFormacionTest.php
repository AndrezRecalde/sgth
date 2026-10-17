<?php

/*
| Los títulos y cursos que el servidor registra desde «Mi perfil» y que
| Talento Humano valida (decisión del 2026-10-17).
|
| Hasta aquí la formación la registraba solo Talento Humano. Ahora el servidor
| la registra con el PDF y queda por validar; Talento Humano la valida o la
| rechaza con motivo, y a la hoja de vida solo llega lo validado.
*/

use App\Enums\EstadoFormacion;
use App\Enums\RegimenLaboral;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Autoservicio\HojaDeVidaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    Storage::fake('local');
    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $unidad = unidadDePrueba(['nombre' => 'Dirección de Talento Humano']);
    $cedula = 800007500;
    $nuevoServidor = function (string $apellido) use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                   => '0'.(++$cedula),
            'nombre'                   => 'Andrea',
            'apellido'                 => $apellido,
            'puesto_id'                => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id' => $unidad->id,
            'regimen_laboral'          => RegimenLaboral::LOSEP,
            'estado'                   => true,
        ]);
    };
    $this->titular   = $nuevoServidor('Titular');
    $this->ajeno     = $nuevoServidor('Ajena');
    $this->deTalento = $nuevoServidor('Analista');

    $this->usuarioDe = function (?Servidor $servidor, string $rol) {
        $usuario = User::create([
            'email'        => uniqid('formacion').'@example.com',
            'usuario_ti'   => uniqid('formacion'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
            'servidor_id'  => $servidor?->id,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };
    $this->servidor = ($this->usuarioDe)($this->titular, 'servidor');
    $this->analista = ($this->usuarioDe)($this->deTalento, 'asistente-uath');

    $this->titulo = [
        'tipo_estudio'         => 'estudio',
        'nivel_estudio'        => 'tercer_nivel',
        'nacionalidad_estudio' => 'nacional',
        'institucion'          => 'Universidad Técnica Luis Vargas Torres',
        'fecha_inicio'         => '2012-04-01',
        'fecha_fin'            => '2017-03-31',
        'titulo_capacitacion'  => 'Licenciada en Administración',
        'codigo_senescyt'      => '1031-17-7654321',
    ];
    $this->pdf = fn () => UploadedFile::fake()->create('titulo.pdf', 300, 'application/pdf');

    $this->registrar = fn (array $extra = []) => $this->actingAs($this->servidor, 'sanctum')
        ->post('/api/v1/autoservicio/mi-formacion-academica', [...$this->titulo, 'documento' => ($this->pdf)(), ...$extra], ['Accept' => 'application/json']);

    $this->porValidarDe = fn (Servidor $servidor) => HistorialAcademicoServidor::create([
        ...$this->titulo,
        'servidor_id'    => $servidor->id,
        'estado'         => EstadoFormacion::POR_VALIDAR,
        'documento_ruta' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf')
            ->store("expediente/formacion/{$servidor->id}", 'local'),
        'documento_nombre' => 'titulo.pdf',
    ]);
});

test('lo que registra el servidor queda por validar, con su PDF guardado', function () {
    $respuesta = ($this->registrar)()->assertCreated();

    $registro = HistorialAcademicoServidor::findOrFail($respuesta->json('datos.id'));
    expect($registro->estado)->toBe(EstadoFormacion::POR_VALIDAR)
        ->and($registro->servidor_id)->toBe($this->titular->id)
        ->and($registro->registrado_por)->toBe($this->servidor->id)
        ->and($respuesta->json('datos.tiene_documento'))->toBeTrue()
        // La ruta del disco no viaja al cliente.
        ->and($respuesta->json('datos'))->not->toHaveKey('documento_ruta');
    Storage::disk('local')->assertExists($registro->documento_ruta);
});

test('sin el PDF no se registra', function () {
    ($this->registrar)(['documento' => null])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['documento']]);

    expect(HistorialAcademicoServidor::count())->toBe(0);
});

test('retira lo suyo por validar, pero no lo validado ni lo ajeno', function () {
    $propio = ($this->porValidarDe)($this->titular);
    $ajeno  = ($this->porValidarDe)($this->ajeno);
    $validado = HistorialAcademicoServidor::create([...$this->titulo, 'servidor_id' => $this->titular->id]);

    $this->actingAs($this->servidor, 'sanctum');

    $this->deleteJson("/api/v1/autoservicio/mi-formacion-academica/{$ajeno->id}")->assertNotFound();
    $this->getJson("/api/v1/autoservicio/mi-formacion-academica/{$ajeno->id}/documento")->assertNotFound();
    $this->deleteJson("/api/v1/autoservicio/mi-formacion-academica/{$validado->id}")->assertStatus(422);

    $ruta = $propio->documento_ruta;
    $this->get("/api/v1/autoservicio/mi-formacion-academica/{$propio->id}/documento")->assertOk();
    $this->deleteJson("/api/v1/autoservicio/mi-formacion-academica/{$propio->id}")->assertOk();

    expect(HistorialAcademicoServidor::find($propio->id))->toBeNull()
        ->and(HistorialAcademicoServidor::find($validado->id))->not->toBeNull();
    Storage::disk('local')->assertMissing($ruta);
});

test('Talento Humano ve lo que espera, lo valida y lo rechaza con motivo', function () {
    $primero = ($this->porValidarDe)($this->titular);
    $segundo = ($this->porValidarDe)($this->ajeno);
    HistorialAcademicoServidor::create([...$this->titulo, 'servidor_id' => $this->ajeno->id]);

    $this->actingAs($this->analista, 'sanctum');

    $lista = $this->getJson('/api/v1/expediente/formacion-por-validar')->assertOk();
    expect(collect($lista->json('datos'))->pluck('id')->all())->toBe([$primero->id, $segundo->id])
        ->and($lista->json('datos.0.servidor.cedula'))->toBe($this->titular->cedula);

    $base = '/api/v1/expediente/servidores';
    $this->get("{$base}/{$this->titular->id}/historial-academico/{$primero->id}/documento")->assertOk();
    $this->postJson("{$base}/{$this->titular->id}/historial-academico/{$primero->id}/validar")->assertOk();
    $this->postJson("{$base}/{$this->ajeno->id}/historial-academico/{$segundo->id}/rechazar", [])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo']]);
    $this->postJson("{$base}/{$this->ajeno->id}/historial-academico/{$segundo->id}/rechazar", [
        'motivo' => 'El PDF no corresponde al título declarado.',
    ])->assertOk();

    $primero->refresh();
    $segundo->refresh();
    expect($primero->estado)->toBe(EstadoFormacion::VALIDADO)
        ->and($primero->validado_por)->toBe($this->analista->id)
        ->and($primero->validado_en)->not->toBeNull()
        ->and($segundo->estado)->toBe(EstadoFormacion::RECHAZADO)
        ->and($segundo->motivo_rechazo)->toBe('El PDF no corresponde al título declarado.');

    // Una sola vez: lo ya resuelto no se vuelve a resolver.
    $this->postJson("{$base}/{$this->titular->id}/historial-academico/{$primero->id}/validar")->assertStatus(422);
    expect($this->getJson('/api/v1/expediente/formacion-por-validar')->json('datos'))->toBe([]);
});

test('nadie de Talento Humano valida lo suyo', function () {
    $propio = ($this->porValidarDe)($this->deTalento);
    $this->actingAs($this->analista, 'sanctum');

    $respuesta = $this->postJson("/api/v1/expediente/servidores/{$this->deTalento->id}/historial-academico/{$propio->id}/validar");

    $respuesta->assertStatus(422);
    expect($respuesta->json('mensaje'))->toContain('su propia formación')
        ->and($propio->fresh()->estado)->toBe(EstadoFormacion::POR_VALIDAR);
});

test('el servidor no entra a la bandeja ni valida', function () {
    $registro = ($this->porValidarDe)($this->titular);
    $this->actingAs($this->servidor, 'sanctum');

    $this->getJson('/api/v1/expediente/formacion-por-validar')->assertForbidden();
    $this->postJson("/api/v1/expediente/servidores/{$this->titular->id}/historial-academico/{$registro->id}/validar")
        ->assertForbidden();
});

test('lo que registra Talento Humano entra validado', function () {
    $this->actingAs($this->analista, 'sanctum');

    $respuesta = $this->postJson("/api/v1/expediente/servidores/{$this->titular->id}/historial-academico", $this->titulo)
        ->assertCreated();

    expect($respuesta->json('datos.estado'))->toBe('validado')
        ->and($respuesta->json('datos.registrado_por'))->toBe($this->analista->id);
});

test('a la hoja de vida solo llega lo validado', function () {
    HistorialAcademicoServidor::create([...$this->titulo, 'servidor_id' => $this->titular->id]);
    ($this->porValidarDe)($this->titular);
    HistorialAcademicoServidor::create([
        ...$this->titulo,
        'servidor_id'         => $this->titular->id,
        'titulo_capacitacion' => 'Rechazado',
        'estado'              => EstadoFormacion::RECHAZADO,
    ]);

    $datos = app(HojaDeVidaService::class)->datos($this->titular->fresh());

    expect($datos['estudios'])->toHaveCount(1)
        ->and($datos['estudios'][0]['titulo'])->toBe('Licenciada en Administración');
});
