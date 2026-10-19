<?php

/*
| Las cargas familiares que el servidor pide desde «Mi perfil», y su validación
| por Talento Humano (docs/diseno-cargas-familiares-portal.md, 2026-10-19).
|
| El Portal ya tuvo un registro directo y se retiró el 2026-10-03: cualquier
| servidor daba de alta familiares que entraban como pacientes del Dispensario.
| Ahora el servidor solo presenta solicitudes, y nada cuenta hasta que Talento
| Humano las valida.
*/

use App\Enums\RegimenLaboral;
use App\Exceptions\ReglaNegocioException;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\CargaFamiliar;
use App\Models\Expediente\Servidor;
use App\Models\Expediente\SolicitudCargaFamiliar;
use App\Models\User;
use App\Services\Dispensario\PacienteService;
use App\Services\Expediente\SolicitudCargaFamiliarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);
    Storage::fake('local');

    $unidad = unidadDePrueba(['nombre' => 'Dirección de Planificación']);
    $cedula = 800009100;
    $nuevoServidor = function (string $apellido) use ($unidad, &$cedula) {
        return Servidor::create([
            'cedula'                    => '0'.(++$cedula),
            'nombre'                    => 'Rosa',
            'apellido'                  => $apellido,
            'puesto_id'                 => puestoDePrueba($unidad)->id,
            'unidad_administrativa_id'  => $unidad->id,
            'regimen_laboral'           => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => '2019-03-01',
            'estado'                    => true,
        ]);
    };

    $this->titular = $nuevoServidor('Titular');
    $this->otro = $nuevoServidor('Otraservidora');
    $this->deTalentoHumano = $nuevoServidor('Analista');

    $usuarioDe = function (Servidor $servidor, string $rol) {
        $usuario = User::create([
            'email'        => uniqid('cargas').'@example.com',
            'usuario_ti'   => uniqid('cargas'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
            'servidor_id'  => $servidor->id,
        ]);
        $usuario->assignRole($rol);

        return $usuario;
    };

    $this->usuarioTitular = $usuarioDe($this->titular, 'servidor');
    $this->usuarioOtro = $usuarioDe($this->otro, 'servidor');
    $this->talentoHumano = $usuarioDe($this->deTalentoHumano, 'admin-uath');

    $this->pdf = fn (string $nombre = 'partida.pdf') => UploadedFile::fake()->create($nombre, 120, 'application/pdf');

    $this->registro = fn (array $cambios = []) => [
        'tipo'             => 'alta',
        'cedula'           => '0850000001',
        'nombres'          => 'Luis Andrés',
        'apellidos'        => 'Cortez Quiñónez',
        'parentesco'       => 'hijo',
        'fecha_nacimiento' => '2015-04-10',
        'genero'           => 'masculino',
        'documento'        => ($this->pdf)(),
        ...$cambios,
    ];

    $this->carga = fn (Servidor $servidor, array $datos = []) => CargaFamiliar::create([
        'servidor_id'      => $servidor->id,
        'cedula'           => '0850000099',
        'nombres'          => 'Ana',
        'apellidos'        => 'Cortez',
        'parentesco'       => 'hijo',
        'fecha_nacimiento' => '2012-01-20',
        'genero'           => 'femenino',
        'estado'           => true,
        ...$datos,
    ]);

    $this->presentar = fn (User $usuario, array $datos) => $this->actingAs($usuario, 'sanctum')
        ->postJson('/api/v1/autoservicio/mis-cargas-familiares/solicitudes', $datos);

    $this->validar = fn (int $id) => $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson("/api/v1/expediente/cargas-por-validar/{$id}/validar");
});

// ── Presentar ────────────────────────────────────────────────────

test('el servidor pide registrar a su hijo con la partida y queda pendiente, sin crear la carga', function () {
    $respuesta = ($this->presentar)($this->usuarioTitular, ($this->registro)())
        ->assertCreated()
        ->assertJsonPath('datos.estado', 'pendiente')
        ->assertJsonPath('datos.tiene_documento', true);

    $solicitud = SolicitudCargaFamiliar::findOrFail($respuesta->json('datos.id'));

    expect(CargaFamiliar::where('cedula', '0850000001')->exists())->toBeFalse()
        ->and($solicitud->servidor_id)->toBe($this->titular->id);
    Storage::disk('local')->assertExists($solicitud->documento_ruta);
});

test('el documento es obligatorio para un hijo y opcional para el cónyuge', function () {
    ($this->presentar)($this->usuarioTitular, ($this->registro)(['documento' => null]))
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['documento']]);

    ($this->presentar)($this->usuarioTitular, ($this->registro)([
        'parentesco' => 'conyugue',
        'genero'     => 'femenino',
        'documento'  => null,
    ]))->assertCreated()->assertJsonPath('datos.tiene_documento', false);
});

test('mientras está pendiente, el Dispensario no lo encuentra como paciente', function () {
    ($this->presentar)($this->usuarioTitular, ($this->registro)())->assertCreated();

    expect(fn () => app(PacienteService::class)->buscarPorCedula('0850000001'))
        ->toThrow(ReglaNegocioException::class);
});

test('la cédula de la carga de otro servidor no se revela', function () {
    ($this->carga)($this->otro, ['cedula' => '0850000002']);

    $respuesta = ($this->presentar)($this->usuarioTitular, ($this->registro)(['cedula' => '0850000002']))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Esta cédula ya consta en el sistema. Consulte con Talento Humano.');

    expect($respuesta->getContent())->not->toContain('Otraservidora');
});

test('no se registra como carga a un servidor del GAD, ni a uno mismo', function () {
    ($this->presentar)($this->usuarioTitular, ($this->registro)(['cedula' => $this->otro->cedula]))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'servidor del GAD'));

    ($this->presentar)($this->usuarioTitular, ($this->registro)(['cedula' => $this->titular->cedula]))
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'No puede registrarse a usted mismo como carga familiar.');
});

test('no se admiten dos solicitudes pendientes para el mismo familiar', function () {
    ($this->presentar)($this->usuarioTitular, ($this->registro)())->assertCreated();

    ($this->presentar)($this->usuarioTitular, ($this->registro)())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Ya tiene una solicitud pendiente para este familiar.');

    // Otro servidor con la misma cédula no se entera de quién la pidió.
    ($this->presentar)($this->usuarioOtro, ($this->registro)())
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Esta cédula ya consta en el sistema. Consulte con Talento Humano.');
});

test('una carga ajena no se puede corregir ni dar de baja', function () {
    $ajena = ($this->carga)($this->otro);

    ($this->presentar)($this->usuarioTitular, ['tipo' => 'baja', 'carga_familiar_id' => $ajena->id, 'motivo' => 'Ya no depende de mí'])
        ->assertNotFound();
});

// ── Validar y rechazar ───────────────────────────────────────────

test('Talento Humano la valida y el familiar queda como carga activa del servidor', function () {
    $id = ($this->presentar)($this->usuarioTitular, ($this->registro)())->json('datos.id');

    ($this->validar)($id)->assertOk()->assertJsonPath('datos.estado', 'validada');

    $carga = CargaFamiliar::where('cedula', '0850000001')->firstOrFail();
    expect($carga->servidor_id)->toBe($this->titular->id)
        ->and($carga->estado)->toBeTrue()
        ->and(SolicitudCargaFamiliar::find($id)->carga_familiar_id)->toBe($carga->id);
});

test('al validar se recupera la fila borrada de esa cédula; al presentar no se toca', function () {
    $borrada = ($this->carga)($this->otro, ['cedula' => '0850000003']);
    $borrada->delete();

    $id = ($this->presentar)($this->usuarioTitular, ($this->registro)(['cedula' => '0850000003']))
        ->assertCreated()
        ->json('datos.id');

    expect(CargaFamiliar::onlyTrashed()->whereKey($borrada->id)->exists())->toBeTrue();

    ($this->validar)($id)->assertOk();

    $recuperada = CargaFamiliar::findOrFail($borrada->id);
    expect($recuperada->servidor_id)->toBe($this->titular->id)
        ->and($recuperada->nombres)->toBe('Luis Andrés')
        ->and(CargaFamiliar::where('cedula', '0850000003')->count())->toBe(1);
});

test('la bandeja avisa lo que quien valida tiene que saber', function () {
    ($this->carga)($this->otro, ['cedula' => '0850000004'])->delete();
    ($this->presentar)($this->usuarioTitular, ($this->registro)(['cedula' => '0850000004']))->assertCreated();
    ($this->presentar)($this->usuarioTitular, ($this->registro)([
        'cedula' => '0850000005', 'parentesco' => 'conyugue', 'genero' => 'femenino', 'documento' => null,
    ]))->assertCreated();

    $bandeja = $this->actingAs($this->talentoHumano, 'sanctum')
        ->getJson('/api/v1/expediente/cargas-por-validar')
        ->assertOk()
        ->json('datos');

    expect($bandeja)->toHaveCount(2)
        ->and($bandeja[0]['avisos'][0])->toContain('se recupera con su historia clínica')
        ->and($bandeja[1]['avisos'][0])->toContain('sin documento de respaldo');
});

test('un solo cónyuge activo: el segundo pide antes la baja del primero, y se valida en orden', function () {
    $actual = ($this->carga)($this->titular, ['cedula' => '0850000010', 'parentesco' => 'conyugue']);
    $nuevo = ($this->registro)(['cedula' => '0850000011', 'parentesco' => 'conyugue', 'genero' => 'femenino', 'documento' => null]);

    ($this->presentar)($this->usuarioTitular, $nuevo)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Ya tiene un cónyuge o conviviente activo. Para registrar a otro, pida primero su baja.');

    $baja = ($this->presentar)($this->usuarioTitular, ['tipo' => 'baja', 'carga_familiar_id' => $actual->id, 'motivo' => 'Divorcio'])
        ->assertCreated()->json('datos.id');
    $alta = ($this->presentar)($this->usuarioTitular, $nuevo)->assertCreated()->json('datos.id');

    ($this->validar)($alta)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'El servidor ya tiene un cónyuge o conviviente activo: valide primero su baja.');

    ($this->validar)($baja)->assertOk();
    ($this->validar)($alta)->assertOk();

    expect($actual->fresh()->estado)->toBeFalse()
        ->and(CargaFamiliar::where('cedula', '0850000011')->value('estado'))->toBeTrue();
});

test('corregir la fecha de un hijo pide documento, y al validar se aplica sin tocar la cédula', function () {
    $hijo = ($this->carga)($this->titular);
    $correccion = [
        'tipo'              => 'correccion',
        'carga_familiar_id' => $hijo->id,
        'nombres'           => 'Ana',
        'apellidos'         => 'Cortez',
        'parentesco'        => 'hijo',
        'fecha_nacimiento'  => '2012-02-20',
        'genero'            => 'femenino',
        // Una cédula distinta no se corrige: el request la descarta.
        'cedula'            => '0850000777',
    ];

    ($this->presentar)($this->usuarioTitular, $correccion)
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['documento']]);

    ($this->presentar)($this->usuarioTitular, [...$correccion, 'fecha_nacimiento' => '2012-01-20'])
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'No cambió ningún dato del familiar.');

    $id = ($this->presentar)($this->usuarioTitular, [...$correccion, 'documento' => ($this->pdf)()])
        ->assertCreated()->json('datos.id');

    ($this->validar)($id)->assertOk();

    expect($hijo->fresh()->fecha_nacimiento->toDateString())->toBe('2012-02-20')
        ->and($hijo->fresh()->cedula)->toBe('0850000099');
});

test('la baja pide motivo, y al validar desactiva al familiar sin borrarlo', function () {
    $hijo = ($this->carga)($this->titular);

    ($this->presentar)($this->usuarioTitular, ['tipo' => 'baja', 'carga_familiar_id' => $hijo->id])
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo']]);

    $id = ($this->presentar)($this->usuarioTitular, ['tipo' => 'baja', 'carga_familiar_id' => $hijo->id, 'motivo' => 'Ya trabaja y no depende de mí'])
        ->assertCreated()->json('datos.id');

    ($this->validar)($id)->assertOk();

    expect($hijo->fresh()->estado)->toBeFalse()
        ->and($hijo->fresh()->trashed())->toBeFalse();
});

test('nadie valida las solicitudes de su propia familia', function () {
    $solicitud = app(SolicitudCargaFamiliarService::class)->presentar(
        $this->talentoHumano,
        ['tipo' => 'alta', 'cedula' => '0850000020', 'nombres' => 'Pedro', 'apellidos' => 'Analista',
            'parentesco' => 'hijo', 'fecha_nacimiento' => '2018-06-01', 'genero' => 'masculino'],
        ($this->pdf)(),
    );

    ($this->validar)($solicitud->id)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', fn (string $m) => str_contains($m, 'su propia familia'));
});

test('se valida una sola vez', function () {
    $id = ($this->presentar)($this->usuarioTitular, ($this->registro)())->json('datos.id');

    ($this->validar)($id)->assertOk();
    ($this->validar)($id)
        ->assertStatus(422)
        ->assertJsonPath('mensaje', 'Esta solicitud ya está validada: solo se valida o se rechaza una pendiente.');
});

test('rechazar pide motivo, y el servidor lo ve en «Mi perfil»', function () {
    $id = ($this->presentar)($this->usuarioTitular, ($this->registro)())->json('datos.id');

    $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson("/api/v1/expediente/cargas-por-validar/{$id}/rechazar")
        ->assertStatus(422)
        ->assertJsonStructure(['errores' => ['motivo']]);

    $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson("/api/v1/expediente/cargas-por-validar/{$id}/rechazar", ['motivo' => 'La partida no es legible'])
        ->assertOk()
        ->assertJsonPath('datos.estado', 'rechazada');

    $this->actingAs($this->usuarioTitular, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-cargas-familiares')
        ->assertOk()
        ->assertJsonPath('datos.solicitudes.0.motivo_rechazo', 'La partida no es legible');
});

// ── «Mi perfil» ──────────────────────────────────────────────────

test('el servidor ve sus cargas y sus solicitudes, sin las condiciones de salud', function () {
    $hijo = ($this->carga)($this->titular, ['observaciones' => 'Nota interna de Talento Humano']);
    ($this->carga)($this->otro, ['cedula' => '0850000098']);
    ($this->presentar)($this->usuarioTitular, ['tipo' => 'baja', 'carga_familiar_id' => $hijo->id, 'motivo' => 'Se emancipó'])
        ->assertCreated();

    $datos = $this->actingAs($this->usuarioTitular, 'sanctum')
        ->getJson('/api/v1/autoservicio/mis-cargas-familiares')
        ->assertOk()
        ->json('datos');

    expect($datos['cargas'])->toHaveCount(1)
        ->and($datos['cargas'][0]['id'])->toBe($hijo->id)
        ->and($datos['cargas'][0]['tiene_solicitud_pendiente'])->toBeTrue()
        ->and($datos['cargas'][0])->not->toHaveKeys(['observaciones', 'persona_con_discapacidad', 'discapacidades'])
        ->and($datos['solicitudes'])->toHaveCount(1);
});

test('retira la suya mientras está pendiente; la ajena responde 404', function () {
    $id = ($this->presentar)($this->usuarioTitular, ($this->registro)())->json('datos.id');

    $this->actingAs($this->usuarioOtro, 'sanctum')
        ->postJson("/api/v1/autoservicio/mis-cargas-familiares/solicitudes/{$id}/retirar")
        ->assertNotFound();
    $this->actingAs($this->usuarioOtro, 'sanctum')
        ->get("/api/v1/autoservicio/mis-cargas-familiares/solicitudes/{$id}/documento")
        ->assertNotFound();

    $this->actingAs($this->usuarioTitular, 'sanctum')
        ->get("/api/v1/autoservicio/mis-cargas-familiares/solicitudes/{$id}/documento")
        ->assertOk();

    $this->actingAs($this->usuarioTitular, 'sanctum')
        ->postJson("/api/v1/autoservicio/mis-cargas-familiares/solicitudes/{$id}/retirar")
        ->assertOk()
        ->assertJsonPath('datos.estado', 'retirada');

    // La retirada ya no estorba: se puede volver a pedir.
    ($this->presentar)($this->usuarioTitular, ($this->registro)())->assertCreated();
});

test('el servidor no entra a la bandeja de Talento Humano', function () {
    $this->actingAs($this->usuarioTitular, 'sanctum')
        ->getJson('/api/v1/expediente/cargas-por-validar')
        ->assertForbidden();
});

// ── Desde el día en que nace (D8) ────────────────────────────────

test('un hijo nacido hoy se registra, desde el Portal y desde el expediente', function () {
    ($this->presentar)($this->usuarioTitular, ($this->registro)(['fecha_nacimiento' => now()->toDateString()]))
        ->assertCreated();

    $this->actingAs($this->talentoHumano, 'sanctum')
        ->postJson("/api/v1/expediente/servidores/{$this->otro->id}/cargas-familiares", [
            'cedula'           => '0850000030',
            'nombres'          => 'Recién',
            'apellidos'        => 'Nacido',
            'parentesco'       => 'hijo',
            'fecha_nacimiento' => now()->toDateString(),
            'genero'           => 'femenino',
        ])
        ->assertCreated();

    ($this->presentar)($this->usuarioTitular, ($this->registro)([
        'cedula' => '0850000031', 'fecha_nacimiento' => now()->addDay()->toDateString(),
    ]))->assertStatus(422)->assertJsonStructure(['errores' => ['fecha_nacimiento']]);
});
