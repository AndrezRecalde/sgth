<?php

/*
| «Mi perfil» del portal: la ficha, la formación y la hoja de vida propias.
|
| El menú llevaba a /portal/mi-perfil, /portal/mi-cv y /portal/academico, y
| ninguna existía. La ficha ya se podía leer (`ServidorPolicy::ver` deja ver la
| propia), pero el historial académico del expediente es solo de Talento
| Humano: el servidor no tenía cómo ver su propia formación.
*/

use App\Enums\RegimenLaboral;
use App\Enums\TipoNombramiento;
use App\Models\Estructura\Puesto;
use App\Models\Estructura\UnidadAdministrativa;
use App\Models\Expediente\ContratoServidor;
use App\Models\Expediente\HistorialAcademicoServidor;
use App\Models\Expediente\Servidor;
use App\Models\User;
use App\Services\Autoservicio\HojaDeVidaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    User::unguard();
    UnidadAdministrativa::unguard();
    Puesto::unguard();
    Servidor::unguard();

    $this->seed(\Database\Seeders\RolPermisoSeeder::class);

    $this->unidad = unidadDePrueba(['nombre' => 'Dirección de Planificación']);

    $cedula = 800007300;
    $nuevoServidor = function (string $apellido) use (&$cedula) {
        return Servidor::create([
            'cedula'                    => '0'.(++$cedula),
            'nombre'                    => 'Rosa',
            'apellido'                  => $apellido,
            'puesto_id'                 => puestoDePrueba($this->unidad)->id,
            'unidad_administrativa_id'  => $this->unidad->id,
            'regimen_laboral'           => RegimenLaboral::LOSEP,
            'fecha_ingreso_institucion' => '2019-03-01',
            'estado_civil'              => 'casado',
            'estado'                    => true,
        ]);
    };

    $this->titular = $nuevoServidor('Titular');
    $this->ajeno   = $nuevoServidor('Ajena');

    $this->usuarioDe = function (?Servidor $servidor, ?string $rol = 'servidor') {
        $usuario = User::create([
            'email'        => uniqid('perfil').'@example.com',
            'usuario_ti'   => uniqid('perfil'),
            'password'     => bcrypt('123456'),
            'primer_login' => false,
            'servidor_id'  => $servidor?->id,
        ]);
        if ($rol) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    };

    $this->titulo = fn (Servidor $servidor, array $datos = []) => HistorialAcademicoServidor::create([
        'servidor_id'          => $servidor->id,
        'tipo_estudio'         => 'estudio',
        'nivel_estudio'        => 'tercer_nivel',
        'nacionalidad_estudio' => 'nacional',
        'institucion'          => 'Universidad Técnica Luis Vargas Torres',
        'fecha_inicio'         => '2010-09-01',
        'fecha_fin'            => '2015-07-31',
        'titulo_capacitacion'  => 'Ingeniera en Sistemas',
        'codigo_senescyt'      => '1031-15-1234567',
        ...$datos,
    ]);
});

test('ve su propia ficha del expediente, pero no la de otro', function () {
    $this->actingAs(($this->usuarioDe)($this->titular), 'sanctum');

    $this->getJson("/api/v1/expediente/servidores/{$this->titular->id}")
        ->assertOk()
        ->assertJsonPath('datos.cedula', $this->titular->cedula);
    $this->getJson("/api/v1/expediente/servidores/{$this->ajeno->id}")->assertForbidden();
});

test('su formación académica trae solo la suya, de la más reciente a la más vieja', function () {
    $vieja = ($this->titulo)($this->titular, ['fecha_inicio' => '2005-01-01', 'titulo_capacitacion' => 'Bachiller']);
    $nueva = ($this->titulo)($this->titular, ['fecha_inicio' => '2018-01-01', 'nivel_estudio' => 'cuarto_nivel']);
    ($this->titulo)($this->ajeno);

    $this->actingAs(($this->usuarioDe)($this->titular), 'sanctum');

    $respuesta = $this->getJson('/api/v1/autoservicio/mi-formacion-academica')->assertOk();

    expect(collect($respuesta->json('datos'))->pluck('id')->all())->toBe([$nueva->id, $vieja->id]);
});

test('sin expediente vinculado, la formación sale vacía y la hoja de vida se explica', function () {
    $this->actingAs(($this->usuarioDe)(null), 'sanctum');

    $this->getJson('/api/v1/autoservicio/mi-formacion-academica')->assertOk()->assertJsonPath('datos', []);
    $this->get('/api/v1/autoservicio/mi-hoja-de-vida')->assertStatus(422);
});

test('quien no tiene acceso al autoservicio no entra', function () {
    $this->actingAs(($this->usuarioDe)($this->titular, null), 'sanctum');

    $this->getJson('/api/v1/autoservicio/mi-formacion-academica')->assertForbidden();
    $this->get('/api/v1/autoservicio/mi-hoja-de-vida')->assertForbidden();
});

test('la hoja de vida sale en PDF con el nombre de su cédula', function () {
    ($this->titulo)($this->titular);
    $this->actingAs(($this->usuarioDe)($this->titular), 'sanctum');

    $respuesta = $this->get('/api/v1/autoservicio/mi-hoja-de-vida')->assertOk();

    expect($respuesta->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))
            ->toContain("hoja_de_vida_{$this->titular->cedula}.pdf")
        ->and(substr($respuesta->getContent(), 0, 4))->toBe('%PDF');
});

test('la hoja de vida separa títulos de cursos y deja fuera los vínculos cancelados', function () {
    ($this->titulo)($this->titular);
    ($this->titulo)($this->titular, [
        'tipo_estudio'        => 'capacitacion',
        'nivel_estudio'       => null,
        'titulo_capacitacion' => 'Gestión por procesos',
        'institucion'         => 'Contraloría General del Estado',
    ]);

    $vinculo = fn (string $estado, string $desde) => ContratoServidor::create([
        'servidor_id'              => $this->titular->id,
        'tipo_nombramiento'        => TipoNombramiento::PERMANENTE->value,
        'unidad_administrativa_id' => $this->unidad->id,
        'puesto_id'                => $this->titular->puesto_id,
        'fecha_inicio'             => $desde,
        'estado'                   => $estado,
    ]);
    $vinculo('vigente', '2019-03-01');
    $vinculo('cancelado', '2018-01-01');

    $datos = app(HojaDeVidaService::class)->datos($this->titular->fresh());

    expect($datos['estudios'])->toHaveCount(1)
        ->and($datos['estudios'][0]['titulo'])->toBe('Ingeniera en Sistemas')
        ->and($datos['capacitaciones'])->toHaveCount(1)
        ->and($datos['capacitaciones'][0]['titulo'])->toBe('Gestión por procesos')
        ->and($datos['trayectoria'])->toHaveCount(1)
        ->and($datos['trayectoria'][0]['desde'])->toBe('01/03/2019')
        ->and($datos['personales']['Estado civil'])->toBe('Casado/a');
});
