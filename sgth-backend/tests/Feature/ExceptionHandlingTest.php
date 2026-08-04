<?php

namespace Tests\Feature;

use App\Models\Expediente\Servidor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('una ruta inexistente responde 404, no 500', function () {
    $response = $this->getJson('/api/v1/esto-no-existe');

    $response->assertStatus(404);
    expect($response->json('errores'))->toBeNull();
});

test('un método HTTP no soportado en una ruta existente responde 405, no 500', function () {
    // GET/HEAD existe para /up (health check), PUT no.
    $response = $this->putJson('/up');

    $response->assertStatus(405);
});

test('con APP_DEBUG=true pero fuera de entorno local, el trace no se expone', function () {
    // La suite corre en entorno "testing" (ver phpunit.xml), no "local":
    // aunque debug esté activo, el trace no debe filtrarse.
    config(['app.debug' => true]);
    expect(app()->environment('local'))->toBeFalse();

    $response = $this->getJson('/api/v1/esto-no-existe');

    $response->assertStatus(404);
    expect($response->json('errores.trace'))->toBeNull();
});

test('una violación de restricción única (QueryException) responde 409 con mensaje claro, no 500', function () {
    // Ruta ad-hoc que salta cualquier validación previa (a propósito, para
    // probar el handler global en aislamiento, no una ruta real de la app
    // — las rutas reales que crean Servidor ya validan unicidad antes).
    Route::post('/api/v1/__test-forzar-violacion-unica', function () {
        Servidor::create([
            'cedula' => '9999999999', 'nombre' => 'Duplicado', 'apellido' => 'Prueba',
            'regimen_laboral' => 'losep', 'estado' => true,
        ]);
    });

    Servidor::create([
        'cedula' => '9999999999', 'nombre' => 'Original', 'apellido' => 'Prueba',
        'regimen_laboral' => 'losep', 'estado' => true,
    ]);

    $response = $this->postJson('/api/v1/__test-forzar-violacion-unica');

    $response->assertStatus(409);
    expect($response->json('mensaje'))->toContain('ya existe');
    expect($response->json('exito'))->toBeFalse();
});
