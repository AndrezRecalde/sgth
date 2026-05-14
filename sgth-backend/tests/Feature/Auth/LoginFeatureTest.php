<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('login exitoso devuelve token y flag primer_login', function () {
    $user = User::factory()->create([
        'usuario_ti'   => 'jperez',
        'password'     => Hash::make('1234567890'),
        'primer_login' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'usuario'    => 'jperez',
        'contrasena' => '1234567890',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'exito',
            'mensaje',
            'datos' => [
                'token',
                'primer_login',
                'usuario'
            ]
        ])
        ->assertJsonPath('datos.primer_login', true);
});

test('intentar acceder a endpoint protegido sin cambiar contraseña devuelve 403', function () {
    $user = User::factory()->create([
        'primer_login' => true,
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/perfil');

    $response->assertStatus(403)
        ->assertJsonPath('mensaje', 'Por seguridad, debe cambiar su contraseña inicial (número de cédula) antes de continuar.');
});

test('el usuario puede cambiar su contraseña obligatoria y luego acceder', function () {
    $user = User::factory()->create([
        'primer_login' => true,
        'password'     => Hash::make('1234567890'),
    ]);

    // Intentamos cambiar la contraseña
    $responseCambio = $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/cambiar-contrasena', [
        'nueva_contrasena' => 'NuevaClave123'
    ]);

    $responseCambio->assertStatus(200);

    // Refrescar el usuario para confirmar que en base de datos el flag cambió a false
    $user->refresh();
    expect($user->primer_login)->toBeFalse();

    // Ahora el acceso al endpoint protegido debe ser permitido
    $responsePerfil = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/perfil');
    
    $responsePerfil->assertStatus(200);
});
