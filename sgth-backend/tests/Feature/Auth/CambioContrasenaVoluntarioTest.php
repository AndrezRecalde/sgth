<?php

use App\Enums\RegimenLaboral;
use App\Models\Expediente\Servidor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, RefreshDatabase::class);

/*
 * Hasta ahora la contraseña solo se cambiaba en el primer acceso. Quien
 * sospechaba que otra persona la conocía no tenía cómo cambiarla sin pedírselo
 * a TI. El cambio voluntario pide la actual y cierra las demás sesiones.
 */

const CLAVE_ACTUAL_VOLUNTARIO = 'ClaveActual123';
const CEDULA_VOLUNTARIO       = '0807654321';

function usuarioQueCambiaSuClave(bool $conPermiso = true): User
{
    $unidad = unidadDePrueba(['nombre' => 'Dirección Financiera']);

    $servidor = Servidor::create([
        'cedula'                   => CEDULA_VOLUNTARIO,
        'nombre'                   => 'Luis',
        'apellido'                 => 'Caicedo',
        'puesto_id'                => puestoDePrueba($unidad, 'Contador')->id,
        'unidad_administrativa_id' => $unidad->id,
        'regimen_laboral'          => RegimenLaboral::LOSEP,
        'estado'                   => true,
    ]);

    $usuario = User::factory()->create([
        'usuario_ti'   => 'lcaicedo',
        'password'     => Hash::make(CLAVE_ACTUAL_VOLUNTARIO),
        'primer_login' => false,
        'servidor_id'  => $servidor->id,
    ]);

    if ($conPermiso) {
        $usuario->givePermissionTo(
            Permission::firstOrCreate(['name' => 'cambiar-contrasena', 'guard_name' => 'sanctum'])
        );
    }

    return $usuario;
}

function iniciarSesionVoluntario(): string
{
    $respuesta = test()->postJson('/api/v1/auth/login', [
        'usuario'    => 'lcaicedo',
        'contrasena' => CLAVE_ACTUAL_VOLUNTARIO,
    ]);

    // El token puede venir en el cuerpo o, cuando el login lo deja en una
    // cookie HttpOnly, solo ahí. Vale para las dos formas.
    return $respuesta->json('datos.token')
        ?? $respuesta->getCookie('sgth_token', decrypt: false)?->getValue();
}

function cambiarClaveCon(string $token, array $datos): TestResponse
{
    return test()->withToken($token)->putJson('/api/v1/auth/contrasena', $datos);
}

test('con la contraseña actual correcta se cambia', function () {
    $usuario = usuarioQueCambiaSuClave();

    cambiarClaveCon(iniciarSesionVoluntario(), [
        'contrasena_actual' => CLAVE_ACTUAL_VOLUNTARIO,
        'nueva_contrasena'  => 'OtraClave456',
    ])->assertOk();

    expect(Hash::check('OtraClave456', $usuario->fresh()->password))->toBeTrue();
});

test('sin la contraseña actual correcta no se cambia', function () {
    $usuario = usuarioQueCambiaSuClave();

    cambiarClaveCon(iniciarSesionVoluntario(), [
        'contrasena_actual' => 'NoEsLaMia999',
        'nueva_contrasena'  => 'OtraClave456',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errores.contrasena_actual.0', 'La contraseña actual no es correcta.');

    expect(Hash::check(CLAVE_ACTUAL_VOLUNTARIO, $usuario->fresh()->password))->toBeTrue();
});

test('la nueva no puede ser la actual ni contener la cédula', function (string $nueva, string $mensaje) {
    usuarioQueCambiaSuClave();

    cambiarClaveCon(iniciarSesionVoluntario(), [
        'contrasena_actual' => CLAVE_ACTUAL_VOLUNTARIO,
        'nueva_contrasena'  => $nueva,
    ])
        ->assertStatus(422)
        ->assertJsonPath('errores.nueva_contrasena.0', $mensaje);
})->with([
    'la misma'     => [CLAVE_ACTUAL_VOLUNTARIO, 'La nueva contraseña debe ser distinta de la actual.'],
    'con cédula'   => ['x' . CEDULA_VOLUNTARIO, 'La nueva contraseña no puede contener su número de cédula.'],
    'sin números'  => ['SoloLetrasAqui', 'La nueva contraseña debe contener letras y números.'],
    'muy corta'    => ['Ab1', 'La nueva contraseña debe tener al menos 8 caracteres.'],
]);

test('cierra las demás sesiones y deja abierta la actual', function () {
    $usuario = usuarioQueCambiaSuClave();

    $otroEquipo = iniciarSesionVoluntario();
    $esteEquipo = iniciarSesionVoluntario();

    cambiarClaveCon($esteEquipo, [
        'contrasena_actual' => CLAVE_ACTUAL_VOLUNTARIO,
        'nueva_contrasena'  => 'OtraClave456',
    ])->assertOk();

    expect($usuario->tokens()->count())->toBe(1);

    app('auth')->forgetGuards();
    test()->withToken($otroEquipo)->getJson('/api/v1/auth/perfil')->assertUnauthorized();

    app('auth')->forgetGuards();
    test()->withToken($esteEquipo)->getJson('/api/v1/auth/perfil')->assertOk();
});

test('sin el permiso cambiar-contrasena no se puede', function () {
    usuarioQueCambiaSuClave(conPermiso: false);

    cambiarClaveCon(iniciarSesionVoluntario(), [
        'contrasena_actual' => CLAVE_ACTUAL_VOLUNTARIO,
        'nueva_contrasena'  => 'OtraClave456',
    ])->assertForbidden();
});

test('quien está en su primer acceso usa la otra ruta', function () {
    $usuario = usuarioQueCambiaSuClave();
    $usuario->update(['primer_login' => true]);

    cambiarClaveCon(iniciarSesionVoluntario(), [
        'contrasena_actual' => CLAVE_ACTUAL_VOLUNTARIO,
        'nueva_contrasena'  => 'OtraClave456',
    ])->assertForbidden();
});

test('adivinar la contraseña actual tiene un límite, con el mensaje en español', function () {
    usuarioQueCambiaSuClave();
    $token = iniciarSesionVoluntario();

    foreach (range(1, 5) as $intento) {
        cambiarClaveCon($token, [
            'contrasena_actual' => "Adivinanza{$intento}",
            'nueva_contrasena'  => 'OtraClave456',
        ])->assertStatus(422);
    }

    cambiarClaveCon($token, [
        'contrasena_actual' => CLAVE_ACTUAL_VOLUNTARIO,
        'nueva_contrasena'  => 'OtraClave456',
    ])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('mensaje', fn (string $mensaje) => str_starts_with($mensaje, 'Demasiados intentos.'));
});
